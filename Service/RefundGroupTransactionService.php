<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\GroupTransaction;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class RefundGroupTransactionService
{
    /**
     * @var float
     */
    private $amountLeftToRefund = 0.0;

    /**
     * @var float
     */
    private $totalOrder = 0.0;

    /**
     * @var BuckarooLog
     */
    private $buckarooLog;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var PaymentGroupTransaction
     */
    private $paymentGroupTransaction;

    /**
     * @var BuilderInterface
     */
    private $requestDataBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $clientInterface;

    /**
     * @var HandlerInterface|null
     */
    private $handler;

    /**
     * @var GiftcardCollection
     */
    private $giftcardCollection;

    /**
     * @param PaymentGroupTransaction  $paymentGroupTransaction
     * @param BuckarooLog              $buckarooLog
     * @param RequestInterface         $request
     * @param BuilderInterface         $requestDataBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface          $clientInterface
     * @param GiftcardCollection       $giftcardCollection
     * @param HandlerInterface|null    $handler
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        BuckarooLog $buckarooLog,
        RequestInterface $request,
        BuilderInterface $requestDataBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $clientInterface,
        GiftcardCollection $giftcardCollection,
        ?HandlerInterface $handler = null
    ) {
        $this->requestDataBuilder = $requestDataBuilder;
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
        $this->paymentGroupTransaction = $paymentGroupTransaction;
        $this->giftcardCollection = $giftcardCollection;
        $this->buckarooLog = $buckarooLog;
        $this->request = $request;
        $this->handler = $handler;
    }

    /**
     * Check if an order has group transactions (giftcards/vouchers/mixed payments)
     * OR is a single giftcard payment (stored in payment additional_information)
     *
     * @param string $orderIncrementId Order increment ID
     * @return bool True if order has giftcard/group transactions, false otherwise
     */
    public function hasGroupTransactions(string $orderIncrementId): bool
    {
        // Check for mixed/partial payments in group_transaction table
        $groupTransactionAmount = $this->paymentGroupTransaction->getGroupTransactionAmount($orderIncrementId);
        if ($groupTransactionAmount > 0) {
            return true;
        }

        // Check for single giftcard payment in payment info
        // We need to check this differently - will handle in refundGroupTransactions
        return false;
    }

    /**
     * Check if payment is a single giftcard payment (not in group_transaction table)
     *
     * @param Payment $payment
     * @return bool
     */
    private function isSingleGiftcardPayment($payment): bool
    {
        return (bool)$payment->getAdditionalInformation('single_giftcard_payment');
    }

    /**
     * Check if transaction method is an actual giftcard
     *
     * @param string|null $transactionMethod
     * @return bool
     */
    private function isActualGiftcard(?string $transactionMethod): bool
    {
        if (!$transactionMethod) {
            return false;
        }

        // Check if it's in the giftcard collection
        $foundGiftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $transactionMethod);

        // Also check for buckaroovoucher
        return $foundGiftcard !== null || $transactionMethod === 'buckaroovoucher';
    }

    /**
     * Refund a single giftcard payment (not in group_transaction table)
     *
     * @param array $buildSubject
     * @param Payment $payment
     * @return float Amount left to refund (should be 0 after refunding full giftcard amount)
     * @throws ConverterException|LocalizedException|ClientException
     */
    private function refundSingleGiftcard(array $buildSubject, $payment): float
    {
        $servicecode = $payment->getAdditionalInformation('single_giftcard_servicecode');
        $transactionId = $payment->getAdditionalInformation('single_giftcard_transaction_id');
        $giftcardAmount = (float)$payment->getAdditionalInformation('single_giftcard_amount');

        // Validate that the servicecode is actually a giftcard
        if (!$this->isActualGiftcard($servicecode)) {
            $this->buckarooLog->addDebug(sprintf(
                '[SINGLE_GIFTCARD_REFUND] | Invalid servicecode: "%s" is not a valid giftcard. Skipping single giftcard refund.',
                $servicecode
            ));
            // Return the full amount to let regular refund flow handle it
            return $this->amountLeftToRefund;
        }

        $this->buckarooLog->addDebug(sprintf(
            '[SINGLE_GIFTCARD_REFUND] | Refunding single giftcard | Service: %s | Amount: %s | Transaction: %s',
            $servicecode,
            $giftcardAmount,
            $transactionId
        ));

        // Determine refund amount (full or partial)
        $refundAmount = min($this->amountLeftToRefund, $giftcardAmount);

        if ($refundAmount > 0) {
            // Build refund request
            $request = $this->requestDataBuilder->build($buildSubject);
            $request['payment_method'] = $servicecode;
            $request['name'] = $servicecode;
            $request['amountCredit'] = $refundAmount;
            $request['originalTransactionKey'] = $transactionId;

            $transferO = $this->transferFactory->create($request);
            $response = $this->clientInterface->placeRequest($transferO);

            if ($this->handler) {
                $this->handler->handle($buildSubject, $response);
            }

            $this->buckarooLog->addDebug(sprintf(
                '[SINGLE_GIFTCARD_REFUND] | Refund response | Amount: %s | Response: %s',
                $refundAmount,
                var_export($response, true)
            ));

            // Update amount left to refund
            $this->amountLeftToRefund -= $refundAmount;

            // Mark as refunded in payment info
            $alreadyRefunded = (float)$payment->getAdditionalInformation('single_giftcard_refunded_amount');
            $payment->setAdditionalInformation('single_giftcard_refunded_amount', $alreadyRefunded + $refundAmount);
        }

        // If fully refunded, mark as complete
        if ($this->amountLeftToRefund < 0.01) {
            $buildSubject['response']['group_transaction_refund_complete'] = true;
            return 0;
        }

        return $this->amountLeftToRefund;
    }

    /**
     * Refund Group Transaction and Return the amount left to refund
     *
     * @param array $buildSubject
     *
     * @throws ClientException
     * @throws ConverterException
     *
     * @return int|mixed|string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function refundGroupTransactions(array &$buildSubject)
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $this->amountLeftToRefund = (float)SubjectReader::readAmount($buildSubject);
        $originalRefundAmount = $this->amountLeftToRefund;

        $order = $paymentDO->getOrder()->getOrder();
        $payment = $paymentDO->getPayment();
        $this->totalOrder = (float)$order->getBaseGrandTotal();

        // Handle single giftcard payment (not in group_transaction table)
        if ($this->isSingleGiftcardPayment($payment)) {
            return $this->refundSingleGiftcard($buildSubject, $payment);
        }

        $requestParams = $this->request->getParams();
        if (!empty($requestParams['creditmemo']['buckaroo_already_paid'])) {
            foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $transaction => $giftCardValue) {
                $this->createRefundGroupRequest($buildSubject, $transaction, $giftCardValue);
            }
        }

        if ($this->amountLeftToRefund >= 0.01 && $originalRefundAmount > $this->amountLeftToRefund) {
            $nonGiftcardTransaction = $this->getNonGiftcardGroupTransaction($order);

            if ($nonGiftcardTransaction) {
                $transactionMethod = strtolower($nonGiftcardTransaction->getData('servicecode'));

                $this->refundRemainingAmount(
                    $buildSubject,
                    $transactionMethod,
                    $this->amountLeftToRefund,
                    $nonGiftcardTransaction->getData('transaction_id')
                );
                $this->amountLeftToRefund = 0;
            }
        }

        if ($this->amountLeftToRefund >= 0.01) {
            $groupTransactionAmount = $this->paymentGroupTransaction->getGroupTransactionAmount(
                $order->getIncrementId()
            );
            if (($groupTransactionAmount > 0.01)
                && empty($requestParams['creditmemo']['buckaroo_already_paid'])
                && !empty($requestParams['creditmemo']['adjustment_negative'])
            ) {
                $order->setAdjustmentNegative(0);
            }
            if ($this->amountLeftToRefund == $order->getBaseGrandTotal() && $groupTransactionAmount > 0) {
                return $this->amountLeftToRefund - $groupTransactionAmount;
            }

            if ($this->amountLeftToRefund > $this->totalOrder) {
                return $this->totalOrder;
            }
            return $this->amountLeftToRefund;
        }

        // If all amount is refunded via group transactions, mark as complete
        // This prevents validators from trying to read a transaction response that doesn't exist
        $buildSubject['response']['group_transaction_refund_complete'] = true;
        return 0;
    }

    /**
     * Create Refund Request for each Giftcard/Voucher/Wallet Transaction
     *
     * @param array  $buildSubject
     * @param string $transaction
     * @param string $giftCardValue
     *
     * @throws ClientException
     * @throws ConverterException
     */
    public function createRefundGroupRequest($buildSubject, $transaction, $giftCardValue)
    {
        $transaction = explode('|', $transaction);
        $this->totalOrder = $this->totalOrder - $transaction[2];

        $groupTransaction = $this->paymentGroupTransaction->getGroupTransactionByTrxId($transaction[0]);

        $this->buckarooLog->addDebug(__METHOD__ . '|10|' . var_export(
            [$giftCardValue, $this->amountLeftToRefund],
            true
        ));

        if ($giftCardValue > 0 && $this->amountLeftToRefund > 0) {
            if ($this->amountLeftToRefund < $giftCardValue) {
                $giftCardValue = $this->amountLeftToRefund;
            }
            $this->amountLeftToRefund = $this->amountLeftToRefund - $giftCardValue;
            $this->buckarooLog->addDebug(__METHOD__ . '|15|' . var_export([$this->amountLeftToRefund], true));

            $request = $this->requestDataBuilder->build($buildSubject);
            $request['payment_method'] = $transaction[1];
            $request['name'] = $transaction[1];
            $request['amountCredit'] = $giftCardValue;
            $request['originalTransactionKey'] = $transaction[0];

            $transferO = $this->transferFactory->create($request);

            $response = $this->clientInterface->placeRequest($transferO);

            if ($this->handler) {
                $this->handler->handle(
                    $buildSubject,
                    $response
                );
            }

            $this->buckarooLog->addDebug(__METHOD__ . '|16| ' . var_export($response, true));

            foreach ($groupTransaction as $item) {
                $prevRefundAmount = $item->getData('refunded_amount');
                $newRefundAmount = $giftCardValue;

                if ($prevRefundAmount !== null) {
                    $newRefundAmount += $prevRefundAmount;
                }
                $item->setData('refunded_amount', $newRefundAmount);
                $item->save();
            }
        }
    }

    /**
     * Return Amount Left To Refund After Refunds Giftcards
     *
     * @return float
     */
    public function getAmountLeftToRefund(): float
    {
        return $this->amountLeftToRefund;
    }

    /**
     * Set the amount left to refund
     *
     * @param float $amountLeftToRefund
     */
    public function setAmountLeftToRefund(float $amountLeftToRefund): void
    {
        $this->amountLeftToRefund = $amountLeftToRefund;
    }

    /**
     * Get the non-giftcard payment transaction from group_transaction table
     *
     * For mixed payments (giftcard + another method), multiple group transactions exist.
     * This method finds and returns the non-giftcard transaction (e.g., ideal).
     *
     * @param Order $order
     * @return GroupTransaction|null Group transaction object or null if not found
     */
    private function getNonGiftcardGroupTransaction($order)
    {
        $groupTransactions = $this->paymentGroupTransaction->getAnyGroupTransactionItems($order->getIncrementId());

        foreach ($groupTransactions as $transaction) {
            $servicecode = $transaction->getData('servicecode');
            // Check if servicecode is not a giftcard variant
            if (!$this->isGiftcardService($servicecode)) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Check if a service code is a giftcard using the giftcard collection
     *
     * This dynamically checks against all configured giftcards instead of hardcoding a list.
     * When new giftcards are added via admin, they will automatically be detected.
     *
     * @param string|null $servicecode
     * @return bool
     */
    private function isGiftcardService(?string $servicecode): bool
    {
        if (!$servicecode) {
            return false;
        }

        // Use the giftcard collection to check if this service code is a giftcard
        // This is the same approach used in PaymentFee helper
        $foundGiftcard = $this->giftcardCollection->getItemByColumnValue(
            'servicecode',
            $servicecode
        );

        return $foundGiftcard !== null;
    }

    /**
     * Refund the remaining amount using the correct payment method
     *
     * For mixed payment scenarios, this method creates a refund request using the
     * non-giftcard payment method (e.g., ideal) for the remaining order amount.
     *
     * @param array $buildSubject
     * @param string $paymentMethod Payment method code (e.g., 'ideal')
     * @param float $amount Amount to refund
     * @param string $originalTransactionKey Transaction key from group_transaction table
     *
     * @throws ClientException
     * @throws ConverterException
     */
    private function refundRemainingAmount(
        array $buildSubject,
        string $paymentMethod,
        float $amount,
        string $originalTransactionKey
    ): void {
        try {
            if (!$originalTransactionKey) {
                $this->buckarooLog->addDebug(
                    __METHOD__ . ' | No transaction key provided for method: ' . $paymentMethod
                );
                return;
            }

            // Build refund request with the correct payment method and transaction key
            $request = $this->requestDataBuilder->build($buildSubject);
            $request['payment_method'] = $paymentMethod;
            $request['name'] = $paymentMethod;
            $request['amountCredit'] = $amount;
            $request['originalTransactionKey'] = $originalTransactionKey;

            $transferO = $this->transferFactory->create($request);
            $response = $this->clientInterface->placeRequest($transferO);

            if ($this->handler) {
                $this->handler->handle($buildSubject, $response);
            }

            $this->buckarooLog->addDebug(
                __METHOD__ . ' | Refunded ' . $amount . ' via ' . $paymentMethod . ' (Key: ' . $originalTransactionKey . ')'
            );

        } catch (\Exception $e) {
            $this->buckarooLog->addDebug(__METHOD__ . ' | ERROR: ' . $e->getMessage());
            throw $e;
        }
    }
}
