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

namespace Buckaroo\Magento2\Service\Refund;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Never ask to refund more than the targeted transaction actually took.
 *
 * A credit memo is priced by Magento, which rounds the discount per invoice, while the capture was
 * sent at reserved prices rounded per unit. The gateway validates a refund against its own
 * transaction, so a memo built from the invoice total can exceed what is refundable.
 */
class RefundCapResolver
{
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
     * @param ArticlesHandlerFactory  $articlesHandlerFactory
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        ArticlesHandlerFactory $articlesHandlerFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->logger = $logger;
    }

    /**
     * Lower the refund amount to what the capture for the memo's invoice actually took.
     *
     * Only ever lowers the amount, and only when the memo targets a single invoice.
     *
     * @param Order         $order
     * @param InfoInterface $payment
     * @param float         $refundAmount
     *
     * @return float
     */
    public function resolveCappedAmount(Order $order, InfoInterface $payment, float $refundAmount): float
    {
        $invoice = $this->resolveCappedInvoice($payment);

        if ($invoice === null) {
            return $refundAmount;
        }

        try {
            $captured = $this->articlesHandlerFactory
                ->create($payment->getMethod())
                ->getCapturedTotalForInvoice($order, $payment, $invoice);
        } catch (\Throwable $e) {
            // A refund must never be blocked by this safety net.
            return $refundAmount;
        }

        $refundable = round($captured - (float)$invoice->getTotalRefunded(), 2);
        $capped = ($refundable > 0 && $refundAmount > $refundable) ? $refundable : $refundAmount;

        $this->logCap($invoice, $refundAmount, $captured, $refundable, $capped);

        return $capped;
    }

    /**
     * The single invoice the credit memo targets, or null when the cap does not apply.
     *
     * @param InfoInterface $payment
     *
     * @return Invoice|null
     */
    private function resolveCappedInvoice(InfoInterface $payment): ?Invoice
    {
        // Only an order payment carries the credit memo; InfoInterface does not declare it.
        if (!$payment instanceof OrderPayment) {
            return null;
        }

        $creditmemo = $payment->getCreditmemo();

        if ($creditmemo === null) {
            return null;
        }

        $invoice = $creditmemo->getInvoice();

        return ($invoice === null || !$invoice->getId()) ? null : $invoice;
    }

    /**
     * Record how the cap was resolved, so a lowered refund can be traced back.
     *
     * @param Invoice $invoice
     * @param float   $requested
     * @param float   $captured
     * @param float   $refundable
     * @param float   $capped
     *
     * @return void
     */
    private function logCap(
        Invoice $invoice,
        float $requested,
        float $captured,
        float $refundable,
        float $capped
    ): void {
        $this->logger->addDebug(sprintf(
            '[REFUND_CAP] invoice %s: creditmemo asks %.4f, invoice grand total %.2f, captured %.2f, '
            . 'already refunded %.2f, refundable %.2f -> sending %.2f',
            $invoice->getIncrementId(),
            $requested,
            (float)$invoice->getGrandTotal(),
            $captured,
            (float)$invoice->getTotalRefunded(),
            $refundable,
            $capped
        ));
    }
}
