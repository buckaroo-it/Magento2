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

namespace Buckaroo\Magento2\Test\Unit\Observer;

/**
 * BTI-1413 — Magento's invoice email template passes the ORDER item to
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
