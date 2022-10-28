<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;

abstract class AbstractInvoiceDataBuilder extends AbstractDataBuilder
{
    private BuckarooHelper $buckarooHelper;
    protected int $numberOfInvoices;
    protected bool $capturePartial = true;
    protected float $currentInvoiceTotal;

    public function __construct(BuckarooHelper $buckarooHelper)
    {
        $this->buckarooHelper = $buckarooHelper;
    }

    public function initialize(array $buildSubject): array
    {
        $data = parent::initialize($buildSubject);

        $order = $this->getOrder();

        $totalOrder = $order->getBaseGrandTotal();
        $this->numberOfInvoices = $order->getInvoiceCollection()->count();
        $this->currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($this->numberOfInvoices) {
            $invoiceCollection = $order->getInvoiceCollection();
            $currentInvoice = $invoiceCollection->getLastItem();
            $this->currentInvoiceTotal = $currentInvoice->getGrandTotal();
        }

        if ($this->buckarooHelper->areEqualAmounts($totalOrder, $this->currentInvoiceTotal) && $this->numberOfInvoices == 1) {
            $this->capturePartial = false; //full capture
        }

        $data['capturePartial'] = $this->capturePartial;
        $data['currentInvoiceTotal'] = $this->currentInvoiceTotal;
        $data['numberOfInvoices'] = $this->numberOfInvoices;

        return $data;
    }
}
