<?php

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;

class RefundGroupTransactionService
{
    /** @var float */
    private $amountLeftToRefund;

    /** @var float */
    private $totalOrder;

    /**
     * @var BuckarooLog
     */
    private BuckarooLog $buckarooLog;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

    private PaymentGroupTransaction $paymentGroupTransaction;
    private BuilderInterface $requestDataBuilder;
    private TransferFactoryInterface $transferFactory;
    private ClientInterface $clientInterface;
    private ?HandlerInterface $handler;

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
    public function refundGroupTransactions($buildSubject)
    {
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $this->amountLeftToRefund = SubjectReader::readAmount($buildSubject);

        $order = $paymentDO->getOrder()->getOrder();
        $this->totalOrder = $order->getBaseGrandTotal();

        $requestParams = $this->request->getParams();
        if (
            isset($requestParams['creditmemo']['buckaroo_already_paid'])
            && !empty($requestParams['creditmemo']['buckaroo_already_paid'])
        ) {
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
            if (
                ($groupTransactionAmount > 0.01)
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
            $this->requestDataBuilder->addData([
                'payment_method' => $transaction[1],
                'amountCredit' => $giftCardValue,
                'originalTransactionKey' => $transaction[0]
            ]);

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