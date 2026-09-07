<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Invoice\Tax;

use Buckaroo\Magento2\Model\Total\Invoice\Tax\BuckarooFee;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * @param array $orderData order-level fee tax data (snake_case keys)
     * @param array $invoiceData invoice totals before collect
     * @return Invoice
     */
    private function createInvoice(array $orderData, array $invoiceData)
    {
        $orderMock = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $orderMock->setData($orderData);

        $invoiceMock = $this->getFakeMock(Invoice::class)->onlyMethods(['getOrder'])->getMock();
        $invoiceMock->method('getOrder')->willReturn($orderMock);
        $invoiceMock->setData($invoiceData);

        return $invoiceMock;
    }

    public function testCollectAddsRemainingFeeTaxToInvoiceTotals(): void
    {
        $invoice = $this->createInvoice(
            [
                'buckaroo_fee_tax_amount'                => 10.0,
                'buckaroo_fee_tax_amount_invoiced'       => 0.0,
                'buckaroo_fee_base_tax_amount'           => 10.0,
                'buckaroo_fee_base_tax_amount_invoiced'  => 0.0,
                'buckaroo_fee_incl_tax'                  => 12.1,
                'buckaroo_fee_incl_tax_invoiced'         => 0.0,
                'base_buckaroo_fee_incl_tax'             => 12.1,
                'base_buckaroo_fee_incl_tax_invoiced'    => 0.0,
            ],
            [
                'grand_total'      => 100.0,
                'base_grand_total' => 100.0,
                'tax_amount'       => 5.0,
                'base_tax_amount'  => 5.0,
            ]
        );

        $this->getInstance()->collect($invoice);

        $this->assertEquals(110.0, $invoice->getGrandTotal());
        $this->assertEquals(110.0, $invoice->getBaseGrandTotal());
        $this->assertEquals(15.0, $invoice->getTaxAmount());
        $this->assertEquals(15.0, $invoice->getBaseTaxAmount());
        $this->assertEquals(10.0, $invoice->getBuckarooFeeTaxAmount());
        $this->assertEquals(10.0, $invoice->getBuckarooFeeBaseTaxAmount());
        $this->assertEquals(12.1, $invoice->getBuckarooFeeInclTax());
        $this->assertEquals(12.1, $invoice->getBaseBuckarooFeeInclTax());
    }

    public function testCollectDoesNotDoubleTaxWhenFeeTaxExceedsInvoiceTotal(): void
    {
        // Remaining fee tax (10.00) >= invoice base grand total (8.00): the invoice
        // grand total is consumed, but the existing invoice tax must NOT be doubled
        $invoice = $this->createInvoice(
            [
                'buckaroo_fee_tax_amount'                => 10.0,
                'buckaroo_fee_tax_amount_invoiced'       => 0.0,
                'buckaroo_fee_base_tax_amount'           => 10.0,
                'buckaroo_fee_base_tax_amount_invoiced'  => 0.0,
                'buckaroo_fee_incl_tax'                  => 12.1,
                'buckaroo_fee_incl_tax_invoiced'         => 0.0,
                'base_buckaroo_fee_incl_tax'             => 12.1,
                'base_buckaroo_fee_incl_tax_invoiced'    => 0.0,
            ],
            [
                'grand_total'      => 8.0,
                'base_grand_total' => 8.0,
                'tax_amount'       => 5.0,
                'base_tax_amount'  => 5.0,
            ]
        );

        $this->getInstance()->collect($invoice);

        $this->assertEquals(0.0, $invoice->getGrandTotal());
        $this->assertEquals(0.0, $invoice->getBaseGrandTotal());
        $this->assertEquals(5.0, $invoice->getTaxAmount(), 'invoice tax must not be doubled');
        $this->assertEquals(5.0, $invoice->getBaseTaxAmount(), 'invoice base tax must not be doubled');
        // The fee tax recorded on this invoice is clamped to the invoice's own tax
        $this->assertEquals(5.0, $invoice->getBuckarooFeeTaxAmount());
        $this->assertEquals(5.0, $invoice->getBuckarooFeeBaseTaxAmount());
    }

    public function testCollectLeavesInvoiceUntouchedWithoutFeeTax(): void
    {
        $invoice = $this->createInvoice(
            [
                'buckaroo_fee_tax_amount'               => 0.0,
                'buckaroo_fee_tax_amount_invoiced'      => 0.0,
                'buckaroo_fee_base_tax_amount'          => 0.0,
                'buckaroo_fee_base_tax_amount_invoiced' => 0.0,
            ],
            [
                'grand_total'      => 50.0,
                'base_grand_total' => 50.0,
                'tax_amount'       => 2.0,
                'base_tax_amount'  => 2.0,
            ]
        );

        $this->getInstance()->collect($invoice);

        $this->assertEquals(50.0, $invoice->getGrandTotal());
        $this->assertEquals(2.0, $invoice->getTaxAmount());
        $this->assertNull($invoice->getBuckarooFeeTaxAmount());
    }
}
