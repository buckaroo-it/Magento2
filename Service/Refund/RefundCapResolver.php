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

namespace Buckaroo\Magento2\Service\Refund;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;

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
     * @var CreditmemoCollectionFactory
     */
    private CreditmemoCollectionFactory $creditmemoCollectionFactory;

    /**
     * Constructor
     *
     * @param ArticlesHandlerFactory      $articlesHandlerFactory
     * @param BuckarooLoggerInterface     $logger
     * @param CreditmemoCollectionFactory $creditmemoCollectionFactory
     */
    public function __construct(
        ArticlesHandlerFactory $articlesHandlerFactory,
        BuckarooLoggerInterface $logger,
        CreditmemoCollectionFactory $creditmemoCollectionFactory
    ) {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->logger = $logger;
        $this->creditmemoCollectionFactory = $creditmemoCollectionFactory;
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
        if (!$payment instanceof OrderPayment) {
            return $refundAmount;
        }

        $creditmemo = $payment->getCreditmemo();
        $invoice = $this->resolveCappedInvoice($creditmemo);

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

        $alreadyRefunded = $this->getAlreadyRefunded($invoice, $creditmemo);
        $refundable = round($captured - $alreadyRefunded, 2);
        $capped = ($refundable > 0 && $refundAmount > $refundable) ? $refundable : $refundAmount;

        $this->logCap($invoice, $refundAmount, $captured, $alreadyRefunded, $refundable, $capped);

        return $capped;
    }

    /**
     * The single invoice the credit memo targets, or null when the cap does not apply.
     *
     * @param Creditmemo|null $creditmemo
     *
     * @return Invoice|null
     */
    private function resolveCappedInvoice(?Creditmemo $creditmemo): ?Invoice
    {
        if ($creditmemo === null) {
            return null;
        }

        $invoice = $creditmemo->getInvoice();

        return ($invoice === null || !$invoice->getId()) ? null : $invoice;
    }

    /**
     * What earlier credit memos already refunded from this invoice, in the currency the capture was sent in.
     *
     * @param Invoice         $invoice
     * @param Creditmemo|null $current the memo being refunded now
     *
     * @return float
     */
    private function getAlreadyRefunded(Invoice $invoice, ?Creditmemo $current): float
    {
        $creditmemos = $this->creditmemoCollectionFactory->create()
            ->addFieldToFilter('invoice_id', ['eq' => (int)$invoice->getId()])
            ->addFieldToFilter('state', ['eq' => Creditmemo::STATE_REFUNDED]);

        if ($current !== null && $current->getId()) {
            $creditmemos->addFieldToFilter('entity_id', ['neq' => (int)$current->getId()]);
        }

        $refunded = 0.0;
        foreach ($creditmemos as $creditmemo) {
            $refunded += round((float)$creditmemo->getGrandTotal(), 2);
        }

        return $refunded;
    }

    /**
     * Record how the cap was resolved, so a lowered refund can be traced back.
     *
     * @param Invoice $invoice
     * @param float   $requested
     * @param float   $captured
     * @param float   $alreadyRefunded
     * @param float   $refundable
     * @param float   $capped
     *
     * @return void
     */
    private function logCap(
        Invoice $invoice,
        float $requested,
        float $captured,
        float $alreadyRefunded,
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
            $alreadyRefunded,
            $refundable,
            $capped
        ));
    }
}
