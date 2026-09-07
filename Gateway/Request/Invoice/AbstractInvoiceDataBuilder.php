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

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Magento\Sales\Model\Order;

abstract class AbstractInvoiceDataBuilder extends AbstractDataBuilder
{
    /**
     * @var int
     */
    protected $numberOfInvoices;

    /**
     * @var bool
     */
    protected $capturePartial = true;

    /**
     * @var float
     */
    protected $currentInvoiceTotal;

    /**
     * @var BuckarooHelper
     */
    private $buckarooHelper;

    /**
     * @var ArticleTotalRegistry
     */
    private ArticleTotalRegistry $articleTotalRegistry;

    /**
     * @param BuckarooHelper       $buckarooHelper
     * @param ArticleTotalRegistry $articleTotalRegistry
     */
    public function __construct(
        BuckarooHelper $buckarooHelper,
        ArticleTotalRegistry $articleTotalRegistry
    ) {
        $this->buckarooHelper = $buckarooHelper;
        $this->articleTotalRegistry = $articleTotalRegistry;
    }

    /**
     * Initializes the payment information for a Buckaroo payment.
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function initialize(array $buildSubject): array
    {
        $data = parent::initialize($buildSubject);

        $order = $this->getOrder();

        $totalOrder = $order->getGrandTotal();
        $this->numberOfInvoices = $order->getInvoiceCollection()->count();
        $this->currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($this->numberOfInvoices) {
            $invoiceCollection = $order->getInvoiceCollection();
            $currentInvoice = $invoiceCollection->getLastItem();
            $this->currentInvoiceTotal = $currentInvoice->getGrandTotal();
        }

        $this->currentInvoiceTotal = $this->resolveCaptureAmount($order, (float)$this->currentInvoiceTotal);

        if ($this->buckarooHelper->areEqualAmounts($totalOrder, $this->currentInvoiceTotal)
            && $this->numberOfInvoices == 1) {
            $this->capturePartial = false; //full capture
        }

        $data['capturePartial'] = $this->capturePartial;
        $data['currentInvoiceTotal'] = $this->currentInvoiceTotal;
        $data['numberOfInvoices'] = $this->numberOfInvoices;

        return $data;
    }

    /**
     * Resolve the amount to capture.
     *
     * @param Order $order
     * @param float $invoiceTotal
     *
     * @return float
     */
    private function resolveCaptureAmount(Order $order, float $invoiceTotal): float
    {
        // The articles handler already caps its lines the same way; this repeats the cap for
        // captures that send no article lines and would otherwise use the invoice total as-is.
        $amount = $this->articleTotalRegistry->get(
            ArticleTotalRegistry::CONTEXT_INVOICE,
            (string)$order->getIncrementId()
        ) ?? $invoiceTotal;

        $remaining = round((float)$order->getGrandTotal() - (float)$order->getTotalPaid(), 2);

        if ($remaining > 0 && $amount > $remaining) {
            return $remaining;
        }

        return $amount;
    }
}
