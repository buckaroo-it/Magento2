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
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;

class RefundGroupTransactionService
{
    /**
     * @var float
     */
    private float $amountLeftToRefund;

    /**
     * @var float
     */
    private float $totalOrder;

    /**
     * @var BuckarooLog
     */
    private BuckarooLog $buckarooLog;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $paymentGroupTransaction;

    /**
     * @var BuilderInterface
     */
    private BuilderInterface $requestDataBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private TransferFactoryInterface $transferFactory;

    /**
     * @var ClientInterface
     */
    private ClientInterface $clientInterface;

    /**
     * @var HandlerInterface|null
     */
    private ?HandlerInterface $handler;

    /**
     * @param PaymentGroupTransaction $paymentGroupTransaction
     * @param BuckarooLog $buckarooLog
     * @param RequestInterface $request
     * @param BuilderInterface $requestDataBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $clientInterface
     * @param HandlerInterface|null $handler
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        BuckarooLog $buckarooLog,
        RequestInterface $request,
        BuilderInterface $requestDataBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $clientInterface,
        HandlerInterface $handler = null
    ) {
        $this->requestDataBuilder = $requestDataBuilder;
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
        $this->paymentGroupTransaction = $paymentGroupTransaction;
        $this->buckarooLog = $buckarooLog;
        $this->request = $request;
        $this->handler = $handler;
    }

    /**
     * Refund Group Transaction and Return the amount left to refund
     *
     * @param array $buildSubject
     * @return int|mixed|string
     * @throws ClientException
     * @throws ConverterException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function refundGroupTransactions(array $buildSubject)
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $this->amountLeftToRefund = (float)SubjectReader::readAmount($buildSubject);

        $order = $paymentDO->getOrder()->getOrder();
        $this->totalOrder = (float)$order->getBaseGrandTotal();

        $requestParams = $this->request->getParams();
        if (!empty($requestParams['creditmemo']['buckaroo_already_paid'])) {
            foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $transaction => $giftCardValue) {
                $this->createRefundGroupRequest($buildSubject, $transaction, $giftCardValue);
            }
        }

        $this->buckarooLog->addDebug(
            __METHOD__ . '|20|' . var_export(
                [$this->amountLeftToRefund, $this->totalOrder, $this->amountLeftToRefund >= 0.01],
                true
            )
        );

        if ($this->amountLeftToRefund >= 0.01) {
            $groupTransactionAmount = $this->paymentGroupTransaction->getGroupTransactionAmount(
                $order->getIncrementId()
            );
            if (($groupTransactionAmount > 0.01)
                && empty($requestParams['creditmemo']['buckaroo_already_paid'])
                && !empty($requestParams['creditmemo']['adjustment_negative'])
            ) {
                $this->buckarooLog->addDebug(__METHOD__ . '|22|');
                $order->setAdjustmentNegative(0);
            }
            if ($this->amountLeftToRefund == $order->getBaseGrandTotal() && $groupTransactionAmount > 0) {
                $this->buckarooLog->addDebug(__METHOD__ . '|25|' . var_export($groupTransactionAmount, true));
                return $this->amountLeftToRefund - $groupTransactionAmount;
            }

            if ($this->amountLeftToRefund > $this->totalOrder) {
                return $this->totalOrder;
            }
            return $this->amountLeftToRefund;
        }
        return 0;
    }

    /**
     * Create Refund Request for each Giftcard/Voucher/Wallet Transaction
     *
     * @param array $buildSubject
     * @param string $transaction
     * @param string $giftCardValue
     * @return void
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
}
