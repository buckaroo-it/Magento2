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
     * The whole run: the row totals the email template overwrites while send() renders are
     * put back once send() returns, together with the order totals.
     */
    public function testExecuteRestoresTheTotalsTheEmailOverwrote(): void
    {
        $orderItem = $this->makeOrderItem(96.00, 90.00);

        $invoiceItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
        $invoiceItem->method('getOrderItem')->willReturn($orderItem);

        $methodInstance = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();
        $methodInstance->method('canCapture')->willReturn(true);

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMock();
        $payment->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['getPayment'])->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->setData(['base_subtotal' => 120.00, 'base_tax_amount' => 21.00, 'base_shipping_amount' => 10.00]);

        $invoice = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub::class)
            ->onlyMethods(['getOrder', 'getAllItems', 'getStore'])
            ->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);
        $invoice->setData(['is_paid' => true, 'email_sent' => false]);

        $accountConfig = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Account')
            ->onlyMethods(['getInvoiceEmail'])
            ->getMock();
        $accountConfig->method('getInvoiceEmail')->willReturn(true);

        $invoiceRepository = $this->getFakeMock('Magento\Sales\Api\InvoiceRepositoryInterface')->getMock();
        $invoiceRepository->expects($this->once())->method('save')->with($invoice);

        $atSend = [];
        $invoiceSender = $this->getFakeMock('Magento\Sales\Model\Order\Email\Sender\InvoiceSender')
            ->onlyMethods(['send'])
            ->getMock();
        $invoiceSender->expects($this->once())->method('send')->with($invoice, true)->willReturnCallback(
            function () use ($orderItem, $order, &$atSend) {
                $atSend = [$orderItem->getRowTotal(), $orderItem->getBaseRowTotal()];
                // What the template does while rendering a partial invoice.
                $orderItem->setRowTotal(64.00);
                $orderItem->setBaseRowTotal(60.00);
                $order->setBaseSubtotal(80.00);
                $order->setBaseTaxAmount(14.00);
                $order->setBaseShippingAmount(0);
                return true;
            }
        );

        $instance = $this->makeObserver([
            'accountConfig' => $accountConfig,
            'invoiceSender' => $invoiceSender,
            'invoiceRepository' => $invoiceRepository,
        ]);

        $instance->execute(new \Magento\Framework\Event\Observer([
            'event' => new \Magento\Framework\Event(['invoice' => $invoice]),
        ]));

        $this->assertSame([96.00, 90.00], $atSend, 'send() sees the totals as they were');
        $this->assertSame(96.00, $orderItem->getRowTotal());
        $this->assertSame(90.00, $orderItem->getBaseRowTotal());
        $this->assertSame(120.00, $order->getBaseSubtotal());
        $this->assertSame(21.00, $order->getBaseTaxAmount());
        $this->assertSame(10.00, $order->getBaseShippingAmount());
    }

    public static function noEmailProvider(): array
    {
        return [
            'email already sent'     => [['is_paid' => true, 'email_sent' => true], true, true],
            'invoice not paid'       => [['is_paid' => false, 'email_sent' => false], true, true],
            'method cannot capture'  => [['is_paid' => true, 'email_sent' => false], false, true],
            'invoice email disabled' => [['is_paid' => true, 'email_sent' => false], true, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noEmailProvider')]
    public function testNoInvoiceEmailIsSentUnlessAllConditionsHold(
        array $invoiceData,
        bool $canCapture,
        bool $emailEnabled
    ): void {
        $this->runExecute($invoiceData, 'buckaroo_magento2_ideal', $canCapture, $emailEnabled, [], 0);
    }

    public function testOtherPaymentMethodsAreIgnored(): void
    {
        $order = $this->runExecute(
            ['is_paid' => true, 'email_sent' => false],
            'checkmo',
            true,
            true,
            ['base_total_paid' => 50.00, 'total_paid' => 60.00],
            0
        )[0];

        $this->assertSame(50.00, $order->getBaseTotalPaid());
    }

    public function testExecuteMarksTheSavedInvoiceAsPersisted(): void
    {
        $invoice = $this->runExecute(
            ['is_paid' => true, 'email_sent' => false, 'grand_total' => 30.00],
            'buckaroo_magento2_ideal',
            true,
            true,
            [],
            1
        )[1];

        $this->assertSame(30.00, $invoice->getOrigData('grand_total'));
    }

    /**
     * Shipping is only put back when the email zeroed it, not when it changed otherwise.
     */
    public function testAChangedNonZeroShippingAmountIsNotPutBack(): void
    {
        $order = $this->runExecute(
            ['is_paid' => true, 'email_sent' => false],
            'buckaroo_magento2_ideal',
            true,
            true,
            ['base_shipping_amount' => 10.00],
            1,
            static function ($order) {
                $order->setBaseShippingAmount(7.00);
            }
        )[0];

        $this->assertSame(7.00, $order->getBaseShippingAmount());
    }

    public function testAfterpayBaseTotalPaidIsAlignedWithTotalPaid(): void
    {
        $order = $this->runExecute(
            ['is_paid' => true, 'email_sent' => true],
            'buckaroo_magento2_afterpay20',
            true,
            true,
            $this->afterpayTotals(),
            0
        )[0];

        $this->assertSame(60.00, $order->getBaseTotalPaid());
    }

    public static function afterpayUntouchedProvider(): array
    {
        return [
            'invoice not paid'        => [false, true, 'buckaroo_magento2_afterpay20', []],
            'method cannot capture'   => [true, false, 'buckaroo_magento2_afterpay20', []],
            'other buckaroo method'   => [true, true, 'buckaroo_magento2_ideal', []],
            'paid amounts equal'      => [true, true, 'buckaroo_magento2_afterpay20', ['base_total_paid' => 60.00]],
            'different currencies'    => [true, true, 'buckaroo_magento2_afterpay20', ['order_currency_code' => 'USD']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('afterpayUntouchedProvider')]
    public function testBaseTotalPaidIsLeftAloneOtherwise(
        bool $isPaid,
        bool $canCapture,
        string $method,
        array $override
    ): void {
        $totals = $override + $this->afterpayTotals();

        $order = $this->runExecute(
            ['is_paid' => $isPaid, 'email_sent' => true],
            $method,
            $canCapture,
            true,
            $totals,
            0
        )[0];

        $this->assertSame($totals['base_total_paid'], $order->getBaseTotalPaid());
    }

    public function testAllCoveredItemsAreRestoredPastSkippedOnes(): void
    {
        $instance = $this->makeObserver();

        $withoutId = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $withoutId->method('getId')->willReturn(null);
        $unchanged = $this->makeOrderItem(10.00, 10.00, 577);
        $first = $this->makeOrderItem(96.00, 90.00, 578);
        $second = $this->makeOrderItem(48.00, 45.00, 579);

        $invoice = $this->makeInvoiceWithItems([$withoutId, $unchanged, $first, $second]);
        $saved = $this->invokeArgs('captureItemRowTotals', [$invoice], $instance);
        $this->assertCount(3, $saved);

        $first->setRowTotal(64.00);
        $first->setBaseRowTotal(60.00);
        $second->setRowTotal(32.00);
        $second->setBaseRowTotal(30.00);

        $notCaptured = $this->makeOrderItem(5.00, 5.00, 580);
        $restoreInvoice = $this->makeInvoiceWithItems([$notCaptured, $unchanged, $first, $second]);
        $this->invokeArgs('restoreItemRowTotals', [$restoreInvoice, $saved], $instance);

        $this->assertSame([96.00, 90.00], [$first->getRowTotal(), $first->getBaseRowTotal()]);
        $this->assertSame([48.00, 45.00], [$second->getRowTotal(), $second->getBaseRowTotal()]);
    }

    public function testOnlyTheBaseRowTotalChangedIsAlsoRestored(): void
    {
        $instance = $this->makeObserver();

        $orderItem = $this->makeOrderItem(96.00, 90.00);
        $invoice = $this->makeInvoice($orderItem);
        $saved = $this->invokeArgs('captureItemRowTotals', [$invoice], $instance);

        $orderItem->setBaseRowTotal(60.00);
        $this->invokeArgs('restoreItemRowTotals', [$invoice, $saved], $instance);

        $this->assertSame(90.00, $orderItem->getBaseRowTotal());
    }

    /**
     * @return array
     */
    private function afterpayTotals(): array
    {
        return [
            'base_total_paid' => 50.00,
            'total_paid' => 60.00,
            'base_currency_code' => 'EUR',
            'order_currency_code' => 'EUR',
        ];
    }

    /**
     * Run execute() on a real-data order and invoice; returns [order, invoice].
     *
     * @param array         $invoiceData
     * @param string        $method
     * @param bool          $canCapture
     * @param bool          $emailEnabled
     * @param array         $orderData
     * @param int           $sendCalls
     * @param callable|null $onSend
     *
     * @return array
     */
    private function runExecute(
        array $invoiceData,
        string $method,
        bool $canCapture,
        bool $emailEnabled,
        array $orderData,
        int $sendCalls,
        ?callable $onSend = null
    ): array {
        $methodInstance = $this->getFakeMock('Magento\Payment\Model\MethodInterface')->getMock();
        $methodInstance->method('canCapture')->willReturn($canCapture);

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')
            ->onlyMethods(['getMethod', 'getMethodInstance'])
            ->getMock();
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['getPayment'])->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->setData($orderData);

        $invoice = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub::class)
            ->onlyMethods(['getOrder', 'getAllItems', 'getStore'])
            ->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getAllItems')->willReturn([]);
        $invoice->setData($invoiceData);

        $accountConfig = $this->getFakeMock('Buckaroo\Magento2\Model\ConfigProvider\Account')
            ->onlyMethods(['getInvoiceEmail'])
            ->getMock();
        $accountConfig->method('getInvoiceEmail')->willReturn($emailEnabled);

        $invoiceRepository = $this->getFakeMock('Magento\Sales\Api\InvoiceRepositoryInterface')->getMock();
        $invoiceRepository->expects($this->exactly($sendCalls))->method('save')->with($invoice);

        $invoiceSender = $this->getFakeMock('Magento\Sales\Model\Order\Email\Sender\InvoiceSender')
            ->onlyMethods(['send'])
            ->getMock();
        $invoiceSender->expects($this->exactly($sendCalls))->method('send')->with($invoice, true)
            ->willReturnCallback(static function () use ($onSend, $order) {
                if ($onSend !== null) {
                    $onSend($order);
                }
                return true;
            });

        $this->makeObserver([
            'accountConfig' => $accountConfig,
            'invoiceSender' => $invoiceSender,
            'invoiceRepository' => $invoiceRepository,
        ])->execute(new \Magento\Framework\Event\Observer([
            'event' => new \Magento\Framework\Event(['invoice' => $invoice]),
        ]));

        return [$order, $invoice];
    }

    /**
     * @param array $orderItems
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeInvoiceWithItems(array $orderItems)
    {
        $invoiceItems = [];
        foreach ($orderItems as $orderItem) {
            $invoiceItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')->getMock();
            $invoiceItem->method('getOrderItem')->willReturn($orderItem);
            $invoiceItems[] = $invoiceItem;
        }

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getAllItems')->willReturn($invoiceItems);

        return $invoice;
    }

    /**
     * @param array $args
     *
     * @return object
     */
    private function makeObserver(array $args = [])
    {
        $helper = $this->getFakeMock('Buckaroo\Magento2\Helper\Data')->getMock();
        $helper->method('areEqualAmounts')->willReturnCallback(
            function ($a, $b) {
                return abs((float)$a - (float)$b) < 0.0001;
            }
        );

        return $this->getInstance(['helper' => $helper] + $args);
    }

    /**
     * @param float $rowTotal
     * @param float $baseRowTotal
     * @param int   $itemId
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrderItem(float $rowTotal, float $baseRowTotal, int $itemId = 579)
    {
        $orderItem = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $orderItem->method('getId')->willReturn($itemId);

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
