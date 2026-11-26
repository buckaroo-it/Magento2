<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter as SdkAdapter;
use Magento\Framework\App\ObjectManager;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
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
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundGroupTransactionService
{
    /** Seconds after the last credit memo before a Buckaroo refund counts as not recorded */
    private const UNRECORDED_REFUND_TOLERANCE = 30;

    /**
     * @var array transaction keys whose unrecorded refunds were already counted in this request
     */
    private array $unrecordedRefundsTaken = [];

    /**
     * Non-Intersolve giftcard brands that reject email/lastname in refund requests.
     */
    private const NON_INTERSOLVE_BRANDS = [
        'fashioncheque',
        'customgiftcard',
    ];

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
     * @var PartialPaymentMethods|null
     */
    private $partialPaymentMethods;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction
     */
    private $groupTransactionResource;

    /**
     * @param PaymentGroupTransaction $paymentGroupTransaction
     * @param BuckarooLog $buckarooLog
     * @param RequestInterface $request
     * @param BuilderInterface $requestDataBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $clientInterface
     * @param GiftcardCollection $giftcardCollection
     * @param \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction $groupTransactionResource
     * @param HandlerInterface|null $handler
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        BuckarooLog $buckarooLog,
        RequestInterface $request,
        BuilderInterface $requestDataBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $clientInterface,
        GiftcardCollection $giftcardCollection,
        \Buckaroo\Magento2\Model\ResourceModel\GroupTransaction $groupTransactionResource,
        ?HandlerInterface $handler = null
    ) {
        $this->groupTransactionResource = $groupTransactionResource;
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
     *
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

        return $this->getPartialPaymentMethods()->isPartialPayment($transactionMethod);
    }

    /**
     * Refund a single giftcard payment (not in group_transaction table).
     *
     * @param array $buildSubject
     * @param Payment $payment
     * @return float Amount left to refund (should be 0 after refunding full giftcard amount)
     * @throws ConverterException|LocalizedException|ClientException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function refundSingleGiftcard(array &$buildSubject, $payment): float
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

            $this->addGiftcardCustomerDetails($request, $buildSubject, (string)$servicecode, 'REFUND_SINGLE_GIFTCARD');

            $transferO = $this->transferFactory->create($request);
            $response = $this->clientInterface->placeRequest($transferO);

            if ($this->handler) {
                $this->handler->handle($buildSubject, $response);
            }

            // Use SDK methods to check response status
            if (isset($response['object']) && $response['object']->isSuccess()) {
                $this->buckarooLog->addDebug(sprintf(
                    '[REFUND_SINGLE_GIFTCARD] | Refund successful | Amount: €%.2f | Status: %s',
                    $refundAmount,
                    $response['object']->getStatusCode()
                ));

                // Update amount left to refund ONLY on success
                $this->amountLeftToRefund = round($this->amountLeftToRefund - $refundAmount, 2);

                // Mark as refunded in payment info
                $alreadyRefunded = (float)$payment->getAdditionalInformation('single_giftcard_refunded_amount');
                $payment->setAdditionalInformation('single_giftcard_refunded_amount', $alreadyRefunded + $refundAmount);
            } else {
                // Refund FAILED - throw exception to prevent credit memo creation
                $errorMessage = isset($response['object'])
                    ? $response['object']->getSomeError()
                    : 'Unknown error - no response object';
                $statusCode = isset($response['object']) ? $response['object']->getStatusCode() : 'N/A';

                $this->buckarooLog->addError(sprintf(
                    '[REFUND_SINGLE_GIFTCARD] | Refund FAILED at Buckaroo | Amount: €%.2f | Status: %s | Message: %s',
                    $refundAmount,
                    $statusCode,
                    $errorMessage
                ));

                throw new \Magento\Payment\Gateway\Http\ClientException(__(
                    'Buckaroo giftcard refund failed (Status %1): %2',
                    $statusCode,
                    $errorMessage
                ));
            }
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
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function refundGroupTransactions(array &$buildSubject)
    {
        $this->unrecordedRefundsTaken = [];
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $this->amountLeftToRefund = (float)SubjectReader::readAmount($buildSubject);
        $originalRefundAmount = $this->amountLeftToRefund;

        $order = $paymentDO->getOrder()->getOrder();
        $payment = $paymentDO->getPayment();
        $this->totalOrder = (float)$order->getBaseGrandTotal();

        $this->buckarooLog->addDebug(sprintf(
            '[REFUND] | Processing refund for order %s | Amount: €%.2f | Payment method: %s',
            $order->getIncrementId(),
            $this->amountLeftToRefund,
            $payment->getMethod()
        ));

        // Handle single giftcard payment (not in group_transaction table)
        if ($this->isSingleGiftcardPayment($payment)) {
            return $this->refundSingleGiftcard($buildSubject, $payment);
        }

        $requestParams = $this->request->getParams();
        if (!empty($requestParams['creditmemo']['buckaroo_already_paid'])) {
            // Admin backend flow: giftcard amounts are explicitly specified in the creditmemo form
            foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $transaction => $giftCardValue) {
                $this->createRefundGroupRequest($buildSubject, $transaction, $giftCardValue);
            }
        } else {
            // API / headless flow (e.g. Returnless): no form params are present.
            // Automatically refund giftcard group transactions proportionally so that
            // the remaining amount passed to the primary payment method never exceeds
            // what was actually charged on that method.
            $this->refundGiftcardTransactionsAutomatically($buildSubject, $order);
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
            if (abs($this->amountLeftToRefund - $order->getBaseGrandTotal()) < 0.01 && $groupTransactionAmount > 0) {
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
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createRefundGroupRequest($buildSubject, $transaction, $giftCardValue)
    {
        $transaction = explode('|', $transaction);
        $this->totalOrder = $this->totalOrder - $transaction[2];

        $groupTransaction = $this->paymentGroupTransaction->getGroupTransactionByTrxId($transaction[0]);

        if ($groupTransaction && $groupTransaction->getEntityId()) {
            // Never credit more than is left on the transaction, e.g. after an earlier partial refund
            $leftOnTransaction = (float)$groupTransaction->getData('amount')
                - (float)$groupTransaction->getRefundedAmount();
            $giftCardValue = min((float)$giftCardValue, max(0.0, $leftOnTransaction));
        }

        if ($giftCardValue > 0 && $this->amountLeftToRefund > 0) {
            if ($this->amountLeftToRefund < $giftCardValue) {
                $giftCardValue = $this->amountLeftToRefund;
            }

            // A refund of this leg that Buckaroo carried out in an attempt whose credit memo failed
            $giftCardValue = $this->applyUnrecordedRefund($buildSubject, $transaction, (float)$giftCardValue, $groupTransaction);
            if ($giftCardValue < 0.01) {
                return;
            }

            // Round to cents: a float difference such as 10.01 - 10.00 is just under 0.01
            $this->amountLeftToRefund = round($this->amountLeftToRefund - $giftCardValue, 2);

            $request = $this->requestDataBuilder->build($buildSubject);
            $request['payment_method'] = $transaction[1];
            $request['name'] = $transaction[1];
            $request['amountCredit'] = $giftCardValue;
            $request['originalTransactionKey'] = $transaction[0];

            $this->addGiftcardCustomerDetails($request, $buildSubject, (string)$transaction[1], 'REFUND_GROUP_TRANSACTION');

            $transferO = $this->transferFactory->create($request);

            $response = $this->clientInterface->placeRequest($transferO);

            if ($this->handler) {
                $this->handler->handle(
                    $buildSubject,
                    $response
                );
            }

            // Use SDK methods to check response status
            if (isset($response['object']) && $response['object']->isSuccess()) {
                $this->buckarooLog->addDebug(sprintf(
                    '[REFUND_GROUP_TRANSACTION] | Refund successful | Service: %s | Amount: €%.2f | Status: %s',
                    $transaction[1],
                    $giftCardValue,
                    $response['object']->getStatusCode()
                ));

                // Update refunded amount in database ONLY on success
                if ($groupTransaction->getEntityId()) {
                    $groupTransaction->setRefundedAmount($groupTransaction->getRefundedAmount() + $giftCardValue);
                    $this->groupTransactionResource->save($groupTransaction);
                }
            } else {
                // Refund FAILED - throw exception to prevent credit memo creation
                $errorMessage = isset($response['object'])
                    ? $response['object']->getSomeError()
                    : 'Unknown error - no response object';
                $statusCode = isset($response['object']) ? $response['object']->getStatusCode() : 'N/A';

                $this->buckarooLog->addError(sprintf(
                    '[REFUND_GROUP_TRANSACTION] | Refund FAILED at Buckaroo | Service: %s | Amount: €%.2f | Status: %s | Message: %s',
                    $transaction[1],
                    $giftCardValue,
                    $statusCode,
                    $errorMessage
                ));

                throw new \Magento\Payment\Gateway\Http\ClientException(__(
                    'Buckaroo giftcard refund failed for %1 (Status %2): %3',
                    $transaction[1],
                    $statusCode,
                    $errorMessage
                ));
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
     * Automatically refund giftcard group transactions for API/headless flows.
     *
     * When a refund is triggered via the REST API (e.g. Returnless), the
     * creditmemo form POST params are not available. This method reads the
     * group_transaction table directly, identifies giftcard transactions and
     * refunds each one proportionally so that only the remainder is sent to
     * the primary payment method (e.g. iDEAL).
     *
     * Example: order paid €10 VVV giftcard + €22 iDEAL = €32 total.
     * Full refund of €32 via API → refund €10 on VVV first → €22 left → refund €22 on iDEAL.
     *
     * @param array $buildSubject
     * @param Order $order
     * @return void
     * @throws ClientException
     * @throws ConverterException
     */
    private function refundGiftcardTransactionsAutomatically(array $buildSubject, Order $order): void
    {
        $groupTransactions = $this->paymentGroupTransaction->getAnyGroupTransactionItems($order->getIncrementId());

        foreach ($groupTransactions as $transaction) {
            $servicecode = $transaction->getData('servicecode');

            if (!$this->isSuccessful($transaction)) {
                // A rejected giftcard or voucher attempt is stored too; there is nothing to refund
                continue;
            }

            if (!$this->isGiftcardService($servicecode)) {
                // Skip non-giftcard transactions; they are handled by the primary refund flow
                continue;
            }

            if ($this->amountLeftToRefund < 0.01) {
                break;
            }

            $availableOnTransaction = (float)$transaction->getData('amount')
                - (float)$transaction->getData('refunded_amount');

            if ($availableOnTransaction < 0.01) {
                continue;
            }

            // Refund as much as needed from this giftcard, capped at what was charged on it
            $refundAmount = min($this->amountLeftToRefund, $availableOnTransaction);

            $this->buckarooLog->addDebug(sprintf(
                '[REFUND_AUTO_GIFTCARD] | Automatically refunding giftcard | Service: %s | Amount: €%.2f | Transaction: %s',
                $servicecode,
                $refundAmount,
                $transaction->getData('transaction_id')
            ));

            // Build the transaction key string in the same format used by createRefundGroupRequest:
            // transactionId|servicecode|amount
            $transactionKey = implode('|', [
                $transaction->getData('transaction_id'),
                $servicecode,
                $transaction->getData('amount'),
            ]);

            $this->createRefundGroupRequest($buildSubject, $transactionKey, $refundAmount);
        }
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
            // Check if servicecode is not a giftcard variant (and the payment succeeded)
            if ($this->isSuccessful($transaction) && !$this->isGiftcardService($servicecode)) {
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

        // Giftcards and the voucher: both are refunded on their own group transaction
        return $this->getPartialPaymentMethods()->isPartialPayment($servicecode);
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
        if (!$originalTransactionKey) {
            $this->buckarooLog->addDebug(sprintf(
                '[REFUND_REMAINING] | No transaction key provided for method: %s',
                $paymentMethod
            ));
            return;
        }

        // A refund of this part that Buckaroo carried out in an attempt whose credit memo failed
        $covered = min($amount, $this->takeUnrecordedRefund($buildSubject, $originalTransactionKey, $paymentMethod));
        if ($covered >= 0.01) {
            $amount -= $covered;
            if ($amount < 0.01) {
                return;
            }
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

        if (isset($response['object']) && $response['object']->isSuccess()) {
            $this->buckarooLog->addDebug(sprintf(
                '[REFUND_REMAINING] | Refund successful | Method: %s | Amount: €%.2f | Status: %s',
                $paymentMethod,
                $amount,
                $response['object']->getStatusCode()
            ));
        } else {
            // Refund FAILED — throw exception to prevent credit memo creation
            $errorMessage = isset($response['object'])
                ? $response['object']->getSomeError()
                : 'Unknown error - no response object';
            $statusCode = isset($response['object']) ? $response['object']->getStatusCode() : 'N/A';

            $this->buckarooLog->addError(sprintf(
                '[REFUND_REMAINING] | Refund FAILED at Buckaroo | Method: %s | Amount: €%.2f | Status: %s | Message: %s',
                $paymentMethod,
                $amount,
                $statusCode,
                $errorMessage
            ));

            throw new \Magento\Payment\Gateway\Http\ClientException(__(
                'Buckaroo refund failed for %1 (Status %2): %3',
                $paymentMethod,
                $statusCode,
                $errorMessage
            ));
        }
    }

    /**
     * Count a refund Buckaroo already carried out on this leg toward this refund, and record it
     *
     * @param array      $buildSubject
     * @param array      $transaction   [key, service code, amount]
     * @param float      $giftCardValue
     * @param mixed      $groupTransaction
     *
     * @return float what is left to refund on the leg
     */
    private function applyUnrecordedRefund(
        array $buildSubject,
        array $transaction,
        float $giftCardValue,
        $groupTransaction
    ): float {
        $covered = min($giftCardValue, $this->takeUnrecordedRefund($buildSubject, $transaction[0], $transaction[1]));
        if ($covered < 0.01) {
            return $giftCardValue;
        }

        $this->amountLeftToRefund = round($this->amountLeftToRefund - $covered, 2);
        if ($groupTransaction && $groupTransaction->getEntityId()) {
            $groupTransaction->setRefundedAmount($groupTransaction->getRefundedAmount() + $covered);
            $this->groupTransactionResource->save($groupTransaction);
        }

        return $giftCardValue - $covered;
    }

    /**
     * Amount Buckaroo refunded on a transaction that no credit memo accounts for, taken once per request
     *
     * A refund whose credit memo failed (or whose answer was lost) rolls back what Magento recorded, but
     * Buckaroo already paid it out. Such refunds are newer than the order's last credit memo; counting them
     * as part of this refund keeps a retry from paying the customer twice.
     *
     * @param array  $buildSubject
     * @param string $transactionKey
     * @param string $paymentMethod
     *
     * @return float
     */
    private function takeUnrecordedRefund(array $buildSubject, string $transactionKey, string $paymentMethod): float
    {
        if (isset($this->unrecordedRefundsTaken[$transactionKey])) {
            return 0.0;
        }
        $this->unrecordedRefundsTaken[$transactionKey] = true;

        try {
            $order = SubjectReader::readPayment($buildSubject)->getOrder()->getOrder();
            $lastCreditmemo = $this->getLastCreditmemoTime($order);
            // Without a credit memo no refund is recorded at all
            $since = $lastCreditmemo === 0 ? 0 : $lastCreditmemo + self::UNRECORDED_REFUND_TOLERANCE;
            $unrecorded = $this->sumRefundsSince($order, $transactionKey, $paymentMethod, $since);
        } catch (\Throwable $e) {
            $this->buckarooLog->addError(sprintf(
                '[REFUND] | Could not check earlier refunds of %s: %s',
                $transactionKey,
                $e->getMessage()
            ));
            return 0.0;
        }

        if ($unrecorded >= 0.01) {
            $this->buckarooLog->addDebug(sprintf(
                '[REFUND] | %s already refunded €%.2f that no credit memo records: counted, not refunded again',
                $transactionKey,
                $unrecorded
            ));
        }

        return round($unrecorded, 2);
    }

    /**
     * Successful Buckaroo refunds of a transaction made after $since
     *
     * @param Order  $order
     * @param string $transactionKey
     * @param string $paymentMethod
     * @param int    $since
     *
     * @throws \Throwable
     *
     * @return float
     */
    private function sumRefundsSince(Order $order, string $transactionKey, string $paymentMethod, int $since): float
    {
        $adapter = ObjectManager::getInstance()->get(SdkAdapter::class);
        $storeId = (int)$order->getStoreId();

        // Refunds point back to the transaction they refund; the original does not always list them
        $candidates = [];
        foreach ($adapter->getTransactionsByInvoice($paymentMethod, $storeId, (string)$order->getIncrementId()) as $listed) {
            $candidates[$listed['key']] = true;
        }
        $original = $adapter->getTransactionStatus($paymentMethod, $storeId, $transactionKey);
        foreach ((array)$original->get('RelatedTransactions') as $related) {
            if (!empty($related['RelatedTransactionKey'])) {
                $candidates[(string)$related['RelatedTransactionKey']] = true;
            }
        }
        unset($candidates[$transactionKey]);

        $sum = 0.0;
        foreach (array_keys($candidates) as $candidateKey) {
            $refund = $adapter->getTransactionStatus($paymentMethod, $storeId, (string)$candidateKey);
            if ($this->isRefundOf($refund, $transactionKey)
                && (int)$refund->getStatusCode() === 190
                && $this->getBuckarooTime($refund) > $since
            ) {
                $sum += (float)$refund->get('AmountCredit');
            }
        }

        return $sum;
    }

    /**
     * Unix time of a Buckaroo transaction status (Buckaroo reports Dutch local time)
     *
     * @param \Buckaroo\Transaction\Response\TransactionResponse $transaction
     *
     * @return int
     */
    private function getBuckarooTime($transaction): int
    {
        $status = (array)$transaction->get('Status');
        if (empty($status['DateTime'])) {
            return 0;
        }

        return (new \DateTimeImmutable((string)$status['DateTime'], new \DateTimeZone('Europe/Amsterdam')))
            ->getTimestamp();
    }

    /**
     * Is refund of
     *
     * @param \Buckaroo\Transaction\Response\TransactionResponse $candidate
     * @param string                                             $originalKey
     *
     * @return bool
     */
    private function isRefundOf($candidate, string $originalKey): bool
    {
        foreach ((array)$candidate->get('RelatedTransactions') as $related) {
            if (strcasecmp((string)($related['RelationType'] ?? ''), 'refund') === 0
                && strcasecmp((string)($related['RelatedTransactionKey'] ?? ''), $originalKey) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get last creditmemo time
     *
     * @param Order $order
     *
     * @return int unix time of the newest credit memo, 0 when there is none
     */
    private function getLastCreditmemoTime(Order $order): int
    {
        $latest = '';
        foreach ($order->getCreditmemosCollection() ?: [] as $creditmemo) {
            if ((string)$creditmemo->getCreatedAt() > $latest) {
                $latest = (string)$creditmemo->getCreatedAt();
            }
        }

        return $latest === '' ? 0 : (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->getTimestamp();
    }

    /**
     * Add customer details only for giftcard brands that accept these fields on refund.
     *
     * @param array  $request
     * @param array  $buildSubject
     * @param string $servicecode
     * @param string $logContext
     */
    private function addGiftcardCustomerDetails(
        array &$request,
        array $buildSubject,
        string $servicecode,
        string $logContext
    ): void {
        if (in_array(strtolower($servicecode), self::NON_INTERSOLVE_BRANDS, true)) {
            $this->buckarooLog->addDebug(sprintf(
                '[%s] | Skipping customer details for non-Intersolve giftcard brand: %s',
                $logContext,
                $servicecode
            ));
            return;
        }

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $billingAddress = $order->getBillingAddress();

        if ($billingAddress) {
            $request['email'] = $order->getCustomerEmail();
            $request['lastname'] = $billingAddress->getLastname();

            $this->buckarooLog->addDebug(sprintf(
                '[%s] | Processing refund | Service: %s | Customer: %s',
                $logContext,
                $servicecode,
                $order->getCustomerEmail()
            ));
            return;
        }

        $this->buckarooLog->addWarning(sprintf(
            '[%s] | Missing billing address for order %s - customer details not added to refund request',
            $logContext,
            $order->getIncrementId()
        ));
    }

    /**
     * Giftcards and the voucher, built from the giftcard collection this class already has
     *
     * @return PartialPaymentMethods
     */
    private function getPartialPaymentMethods(): PartialPaymentMethods
    {
        if ($this->partialPaymentMethods === null) {
            $this->partialPaymentMethods = new PartialPaymentMethods($this->giftcardCollection);
        }

        return $this->partialPaymentMethods;
    }

    /**
     * Whether the group transaction is a successful payment (failed attempts are stored as well)
     *
     * @param GroupTransaction $transaction
     *
     * @return bool
     */
    private function isSuccessful($transaction): bool
    {
        return (int)$transaction->getData('status') === BuckarooStatusCode::SUCCESS;
    }
}
