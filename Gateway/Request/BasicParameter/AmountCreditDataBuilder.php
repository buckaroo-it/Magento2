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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Service\RefundGroupTransactionService;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use InvalidArgumentException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;

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
     * @var ArticlesHandlerFactory
     */
    private ArticlesHandlerFactory $articlesHandlerFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * Constructor
     *
     * @param TransactionCurrencyResolver   $transactionCurrencyResolver
     * @param RefundGroupTransactionService $refundGroupService
     * @param ArticlesHandlerFactory        $articlesHandlerFactory
     * @param BuckarooLoggerInterface       $logger
     */
    public function __construct(
        TransactionCurrencyResolver $transactionCurrencyResolver,
        RefundGroupTransactionService $refundGroupService,
        ArticlesHandlerFactory $articlesHandlerFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->transactionCurrencyResolver = $transactionCurrencyResolver;
        $this->refundGroupService = $refundGroupService;
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->logger = $logger;
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
        $this->capAtCapturedAmount($order, $payment);

        return [
            self::AMOUNT_CREDIT => $this->getRefundAmount()
        ];
    }

    /**
     * Never ask to refund more than the targeted transaction actually took.
     *
     * A credit memo is priced by Magento, which rounds the discount per invoice, while the capture
     * was sent at reserved prices rounded per unit. The gateway validates a refund against its own
     * transaction, so a memo built from the invoice total can exceed what is refundable.
     *
     * Only ever lowers the amount, and only when the memo targets a single invoice.
     *
     * @param Order         $order
     * @param InfoInterface $payment
     *
     * @return void
     */
    private function capAtCapturedAmount(Order $order, InfoInterface $payment): void
    {
        // Only an order payment carries the credit memo; InfoInterface does not declare it.
        if (!$payment instanceof OrderPayment) {
            return;
        }

        $creditmemo = $payment->getCreditmemo();

        if ($creditmemo === null) {
            return;
        }

        $invoice = $creditmemo->getInvoice();

        if ($invoice === null || !$invoice->getId()) {
            return;
        }

        try {
            $captured = $this->articlesHandlerFactory
                ->create($payment->getMethod())
                ->getCapturedTotalForInvoice($order, $payment, $invoice);
        } catch (\Throwable $e) {
            // A refund must never be blocked by this safety net.
            return;
        }

        $refundable = round($captured - (float)$invoice->getTotalRefunded(), 2);

        $this->logger->addDebug(sprintf(
            '[REFUND_CAP] invoice %s: creditmemo asks %.4f, invoice grand total %.2f, captured %.2f, '
            . 'already refunded %.2f, refundable %.2f -> sending %.2f',
            $invoice->getIncrementId(),
            $this->refundAmount,
            (float)$invoice->getGrandTotal(),
            $captured,
            (float)$invoice->getTotalRefunded(),
            $refundable,
            ($refundable > 0 && $this->refundAmount > $refundable) ? $refundable : $this->refundAmount
        ));

        if ($refundable > 0 && $this->refundAmount > $refundable) {
            $this->refundAmount = $refundable;
        }
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
