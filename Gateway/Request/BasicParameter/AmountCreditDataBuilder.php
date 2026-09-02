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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\Refund\RefundCapResolver;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use InvalidArgumentException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class AmountCreditDataBuilder implements BuilderInterface
{
    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    public const AMOUNT_CREDIT = 'amountCredit';

    /**
     * @var float
     */
    public $refundAmount;

    /**
     * @var TransactionCurrencyResolver
     */
    private $transactionCurrencyResolver;

    /**
     * @var RefundGroupTransactionService
     */
    private $refundGroupService;

    /**
     * @var RefundCapResolver
     */
    private RefundCapResolver $refundCapResolver;

    /**
     * Constructor
     *
     * @param TransactionCurrencyResolver   $transactionCurrencyResolver
     * @param RefundGroupTransactionService $refundGroupService
     * @param RefundCapResolver             $refundCapResolver
     */
    public function __construct(
        TransactionCurrencyResolver $transactionCurrencyResolver,
        RefundGroupTransactionService $refundGroupService,
        RefundCapResolver $refundCapResolver
    ) {
        $this->transactionCurrencyResolver = $transactionCurrencyResolver;
        $this->refundGroupService = $refundGroupService;
        $this->refundCapResolver = $refundCapResolver;
    }

    /**
     * @inheritdoc
     *
     * @throws InvalidArgumentException
     * @throws ClientException
     * @throws ConverterException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $baseAmountToRefund = $buildSubject['amount'] ?? $order->getBaseGrandTotal();
        $this->refundAmount = (float)$baseAmountToRefund;

        if ($this->refundAmount < 0.01) {
            throw new InvalidArgumentException('Credit Amount must be greater than 0');
        }

        $payment = $paymentDO->getPayment();

        // Check for group transactions (mixed/partial payments) OR single giftcard payments
        $hasGroupTransactions = $this->refundGroupService->hasGroupTransactions($order->getIncrementId());
        $isSingleGiftcard = (bool)$payment->getAdditionalInformation('single_giftcard_payment');

        $amountAdjustedForGroupTransactions = false;
        if ($hasGroupTransactions || $isSingleGiftcard) {
            // The return value carries the clamped remainder (group-transaction deduction,
            // total-order ceiling) - the raw amountLeftToRefund property does not
            $this->refundAmount = (float)$this->refundGroupService->refundGroupTransactions($buildSubject);
            $amountAdjustedForGroupTransactions = true;

            if ($this->refundAmount < 0.01) {
                $payment->setIsTransactionClosed(true);
                $payment->setShouldCloseParentTransaction(true);
            }
        }

        $this->setRefundAmount($order, $payment, $amountAdjustedForGroupTransactions);
        $this->refundAmount = $this->refundCapResolver->resolveCappedAmount($order, $payment, $this->refundAmount);

        if ($this->refundAmount < 0.01) {
            throw new InvalidArgumentException('Credit Amount must be greater than 0');
        }

        return [
            self::AMOUNT_CREDIT => $this->getRefundAmount()
        ];
    }

    /**
     * Get Refund Amount
     *
     * @return float
     */
    public function getRefundAmount()
    {
        return $this->refundAmount;
    }

    /**
     * Set Refund Amount Based on Currency
     *
     * Magento hands the refund amount in base currency (Payment::refund() passes the
     * creditmemo's base grand total), while the transaction was sent in the order
     * currency. Prefer the creditmemo's stored order-currency grand total over
     * converting the base amount by rate: Magento computed it directly from the
     * order-currency prices, so repeated partial refunds cannot accumulate rounding
     * drift. The base-to-order rate conversion remains only for the group-transaction
     * remainder, which is tracked in base currency by RefundGroupTransactionService.
     *
     * @param Order         $order
     * @param InfoInterface $payment
     * @param bool          $amountAdjustedForGroupTransactions
     * @throws Exception
     */
    protected function setRefundAmount(
        Order $order,
        InfoInterface $payment,
        bool $amountAdjustedForGroupTransactions = false
    ) {
        $transactionCurrency = $this->transactionCurrencyResolver->resolve($order, $payment->getMethodInstance());
        if ($transactionCurrency != $order->getOrderCurrencyCode()
            || $order->getOrderCurrencyCode() == $order->getBaseCurrencyCode()
        ) {
            return;
        }

        $creditmemo = $payment->getCreditmemo();
        if (!$amountAdjustedForGroupTransactions && $creditmemo !== null) {
            $this->refundAmount = (float)$creditmemo->getGrandTotal();
            return;
        }

        $this->refundAmount = round($this->refundAmount * $order->getBaseToOrderRate(), 2);
    }
}
