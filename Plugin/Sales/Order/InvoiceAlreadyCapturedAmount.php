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

namespace Buckaroo\Magento2\Plugin\Sales\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\Service\UninvoicedCaptures;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Invoice money Buckaroo already captured instead of capturing it again.
 *
 * Every online invoice capture runs through Invoice::capture() - the invoice created on shipment,
 * an invoice made in the admin, and the "Capture" button on an open invoice. When a capture was
 * made outside Magento (in Buckaroo Plaza) before the order shipped, sending the invoice to the
 * gateway asks for money that is no longer on the reservation, and Buckaroo refuses it.
 *
 * - The recorded capture covers the invoice: the invoice is paid without a gateway call and is
 *   linked to that capture, so it can still be refunded online.
 * - It covers part of the last Klarna KP invoice: only the rest is captured. Klarna takes an
 *   amount without order lines only when it equals what is left on the reservation, which is
 *   exactly this remainder.
 * - Otherwise the invoice is captured as before.
 */
class InvoiceAlreadyCapturedAmount
{
    /**
     * @var UninvoicedCaptures
     */
    private UninvoicedCaptures $uninvoicedCaptures;

    /**
     * @var CheckPaymentType
     */
    private CheckPaymentType $checkPaymentType;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param UninvoicedCaptures      $uninvoicedCaptures
     * @param CheckPaymentType        $checkPaymentType
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        UninvoicedCaptures $uninvoicedCaptures,
        CheckPaymentType $checkPaymentType,
        BuckarooLoggerInterface $logger
    ) {
        $this->uninvoicedCaptures = $uninvoicedCaptures;
        $this->checkPaymentType = $checkPaymentType;
        $this->logger = $logger;
    }

    /**
     * Pay the invoice from an earlier capture where it covers it.
     *
     * @param Invoice  $subject
     * @param callable $proceed
     *
     * @return Invoice
     */
    public function aroundCapture(Invoice $subject, callable $proceed)
    {
        $order = $subject->getOrder();
        $payment = $order->getPayment();

        if (!$this->checkPaymentType->isBuckarooPayment($payment)) {
            return $proceed();
        }

        $captured = $this->uninvoicedCaptures->getAmount($order);
        $invoiceTotal = round((float)$subject->getGrandTotal(), 2);

        if ($captured < 0.01 || $invoiceTotal < 0.01) {
            return $proceed();
        }

        if ($captured >= $invoiceTotal) {
            return $this->payFromEarlierCapture($subject, $order, $invoiceTotal);
        }

        if ($this->isLastKlarnaKpInvoice($order)) {
            return $this->captureRemainder($subject, $order, $proceed, $captured, $invoiceTotal);
        }

        $this->logger->addDebug(sprintf(
            '[UNINVOICED_CAPTURE] | [%s:%s] - Order %s: %s captured earlier does not cover invoice total %s;'
            . ' capturing the invoice as usual',
            __METHOD__,
            __LINE__,
            $order->getIncrementId(),
            $captured,
            $invoiceTotal
        ));

        return $proceed();
    }

    /**
     * Pay the invoice without a gateway call and link it to the earlier capture.
     *
     * @param Invoice $invoice
     * @param Order   $order
     * @param float   $invoiceTotal
     *
     * @return Invoice
     */
    private function payFromEarlierCapture(Invoice $invoice, Order $order, float $invoiceTotal): Invoice
    {
        $transactionKey = $this->uninvoicedCaptures->getTransactionKey($order);

        if ($transactionKey !== '') {
            $invoice->setTransactionId($transactionKey);
        }
        $invoice->setCanVoidFlag(false);
        $invoice->pay();

        $this->uninvoicedCaptures->consume($order, $invoiceTotal);

        $order->addCommentToStatusHistory((string)__(
            'Buckaroo: invoiced %1 from the amount already captured at Buckaroo (transaction %2).'
            . ' No new capture was requested.',
            $this->format($order, $invoiceTotal),
            $transactionKey
        ));

        $this->logger->addDebug(sprintf(
            '[UNINVOICED_CAPTURE] | [%s:%s] - Order %s: invoice of %s paid from earlier capture %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId(),
            $invoiceTotal,
            $transactionKey
        ));

        return $invoice;
    }

    /**
     * Capture only the part of the invoice the earlier capture does not cover.
     *
     * @param Invoice  $invoice
     * @param Order    $order
     * @param callable $proceed
     * @param float    $captured
     * @param float    $invoiceTotal
     *
     * @return Invoice
     */
    private function captureRemainder(
        Invoice $invoice,
        Order $order,
        callable $proceed,
        float $captured,
        float $invoiceTotal
    ) {
        $remainder = round($invoiceTotal - $captured, 2);
        $invoice->setData(UninvoicedCaptures::INVOICE_CAPTURE_REMAINDER, $remainder);

        $this->logger->addDebug(sprintf(
            '[UNINVOICED_CAPTURE] | [%s:%s] - Order %s: %s of invoice total %s captured earlier;'
            . ' capturing the remaining %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId(),
            $captured,
            $invoiceTotal,
            $remainder
        ));

        try {
            $result = $proceed();
        } finally {
            $invoice->unsetData(UninvoicedCaptures::INVOICE_CAPTURE_REMAINDER);
        }

        $this->uninvoicedCaptures->consume($order, $captured);

        $order->addCommentToStatusHistory((string)__(
            'Buckaroo: %1 of this invoice was already captured at Buckaroo; only the remaining %2 was captured.',
            $this->format($order, $captured),
            $this->format($order, $remainder)
        ));

        return $result;
    }

    /**
     * Whether this is a Klarna KP invoice that leaves nothing else to invoice.
     *
     * Invoice::register() books the invoiced quantities before it captures, so at this point
     * canInvoice() already reflects this invoice.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isLastKlarnaKpInvoice(Order $order): bool
    {
        return $order->getPayment()->getMethod() === Klarnakp::CODE && !$order->canInvoice();
    }

    /**
     * Format an amount in the order currency for an order comment.
     *
     * @param Order $order
     * @param float $amount
     *
     * @return string
     */
    private function format(Order $order, float $amount): string
    {
        return (string)$order->getOrderCurrency()->formatTxt($amount);
    }
}
