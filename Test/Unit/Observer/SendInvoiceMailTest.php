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

namespace Buckaroo\Magento2\Test\Unit\Observer;

/**
 * Magento's invoice email template passes the ORDER item to
 * DefaultItems::getItemPrice(), which writes row_total = orderItem.price * invoiceItem.qty onto
 * it. On a partial invoice that is wrong (2 of 3 units gives 64.00 for a 96.00 row), and the
 * next save of the order commits it, so credit memos are pro-rated from a corrupted figure.
 *
 * Reproduced on a plain checkmo order with no payment plugin involved, so this is a core defect
 * we contain rather than one we caused.
 */
class SendInvoiceMailTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Observer\SendInvoiceMail';

    public function testTheRowTotalsTheEmailOverwritesAreRestored(): void
    {
        $instance = $this->makeObserver();

        $orderItem = $this->makeOrderItem(96.00, 96.00);
        $invoice = $this->makeInvoice($orderItem);

        $saved = $this->invokeArgs('captureItemRowTotals', [$invoice], $instance);

        // What Magento's template does while rendering: price 32.00 x invoiced qty 2.
        $orderItem->setRowTotal(64.00);
        $orderItem->setBaseRowTotal(64.00);

        $this->invokeArgs('restoreItemRowTotals', [$invoice, $saved], $instance);

        $this->assertEquals(96.00, $orderItem->getRowTotal());
        $this->assertEquals(96.00, $orderItem->getBaseRowTotal());
    }

    /**
     * A full invoice leaves the value alone, so nothing is written back needlessly.
     */
    public function testUntouchedTotalsAreLeftAlone(): void
    {
        $instance = $this->makeObserver();

        $orderItem = $this->makeOrderItem(96.00, 96.00);
        $orderItem->expects($this->never())->method('setRowTotal');

        $invoice = $this->makeInvoice($orderItem);
        $saved = $this->invokeArgs('captureItemRowTotals', [$invoice], $instance);

        $this->invokeArgs('restoreItemRowTotals', [$invoice, $saved], $instance);
    }

    /**
     * An invoice item with no order item behind it must not blow up.
     */
    public function testAnInvoiceItemWithoutAnOrderItemIsSkipped(): void
    {
        $instance = $this->makeObserver();

        $invoiceItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $invoiceItem->method('getOrderItem')->willReturn(null);

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);

        $this->assertSame([], $this->invokeArgs('captureItemRowTotals', [$invoice], $instance));
        $this->invokeArgs('restoreItemRowTotals', [$invoice, []], $instance);
    }

    /**
     * The core observers that accumulate an order-level amount over its invoices
     * (Magento\CustomerBalance\Observer\IncreaseOrderInvoicedAmountObserver and the gift card and
     * reward observers built the same way) recognise a newly created invoice by an empty origData,
     * and this observer's save is that invoice's INSERT - it runs inside Invoice::register(),
     * before the caller saves the same object again. Unless the object records that it now exists,
     * every one of those amounts is counted twice: an order with 30.00 of store credit ended up
     * with base_customer_balance_invoiced 60.00, and the next invoice was then handed a NEGATIVE
     * store credit that inflated its grand total.
     */
    public function testTheInvoiceRecordsThatTheSaveHasPersistedIt(): void
    {
        $instance = $this->makeObserver();

        $invoice = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $invoice->setData('customer_balance_amount', 30.00);

        $this->assertNull($invoice->getOrigData(), 'An unsaved invoice has no original data');

        $this->invokeArgs('markAsPersisted', [$invoice], $instance);

        $this->assertSame(
            30.00,
            $invoice->getOrigData('customer_balance_amount'),
            'After the save the invoice must no longer look newly created'
        );
    }

    /**
     * @return object
     */
    private function makeObserver()
    {
        $helper = $this->getFakeMock('Buckaroo\Magento2\Helper\Data')->getMock();
        $helper->method('areEqualAmounts')->willReturnCallback(
            function ($a, $b) {
                return abs((float)$a - (float)$b) < 0.0001;
            }
        );

        return $this->getInstance(['helper' => $helper]);
    }

    /**
     * @param float $rowTotal
     * @param float $baseRowTotal
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrderItem(float $rowTotal, float $baseRowTotal)
    {
        $orderItem = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $orderItem->method('getId')->willReturn(579);

        $current = ['row_total' => $rowTotal, 'base_row_total' => $baseRowTotal];
        $orderItem->method('getRowTotal')->willReturnCallback(
            function () use (&$current) {
                return $current['row_total'];
            }
        );
        $orderItem->method('getBaseRowTotal')->willReturnCallback(
            function () use (&$current) {
                return $current['base_row_total'];
            }
        );
        $orderItem->method('setRowTotal')->willReturnCallback(
            function ($value) use (&$current, $orderItem) {
                $current['row_total'] = $value;
                return $orderItem;
            }
        );
        $orderItem->method('setBaseRowTotal')->willReturnCallback(
            function ($value) use (&$current, $orderItem) {
                $current['base_row_total'] = $value;
                return $orderItem;
            }
        );

        return $orderItem;
    }

    /**
     * @param object $orderItem
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeInvoice($orderItem)
    {
        $invoiceItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $invoiceItem->method('getOrderItem')->willReturn($orderItem);

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);

        return $invoice;
    }
}
