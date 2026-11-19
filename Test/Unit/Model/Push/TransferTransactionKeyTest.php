<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Each bank transfer payment is applied to the order totals once per transaction key.
 */
class TransferTransactionKeyTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\TransferProcessor';

    /** @var array */
    private $totals;

    /** @var array */
    private $additionalInformation;

    public function testSamePartialPaymentIsAppliedOnce(): void
    {
        $instance = $this->getInstanceWith('trx-1', 10.00, 1);

        $first = ['amount' => 10.00, 'description' => ''];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$first], $instance));
        $this->assertEquals(Order::STATE_NEW, $first['state']);
        $this->assertEqualsWithDelta(10.05, $this->totals['due'], 0.001);
        $this->assertEqualsWithDelta(10.00, $this->totals['paid'], 0.001);

        $second = ['amount' => 10.00, 'description' => ''];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$second], $instance));
        $this->assertEqualsWithDelta(10.05, $this->totals['due'], 0.001);
        $this->assertEqualsWithDelta(10.00, $this->totals['paid'], 0.001);
        $this->assertEqualsWithDelta(10.05, $this->totals['baseDue'], 0.001);
        $this->assertEqualsWithDelta(10.00, $this->totals['basePaid'], 0.001);
        // the repeated payment leaves the order state and status as they are
        $this->assertSame(Order::STATE_NEW, $second['state']);
        $this->assertSame('pending', $second['newStatus']);
    }

    public function testNewTransactionKeyIsAppliedAgain(): void
    {
        $instance = $this->getInstanceWith('trx-1', 10.00, 2);
        $details = ['amount' => 10.00, 'description' => ''];
        $this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance);

        $this->setProperty('pushRequest', $this->createRequest('trx-2', 5.00), $instance);
        $details = ['amount' => 5.00, 'description' => ''];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));

        $this->assertEqualsWithDelta(5.05, $this->totals['due'], 0.001);
        $this->assertEqualsWithDelta(15.00, $this->totals['paid'], 0.001);
    }

    public function testFinalPaymentStillInvoices(): void
    {
        $instance = $this->getInstanceWith('trx-9', 20.05, 0);
        $details = ['amount' => 20.05, 'description' => ''];

        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));
        $this->assertArrayNotHasKey('state', $details);
    }

    public function testRepeatedPaymentAboveRemainingDueDoesNotInvoice(): void
    {
        $instance = $this->getInstanceWith('trx-1', 15.00, 1);
        $details = ['amount' => 15.00, 'description' => ''];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));

        // the order now owes less than the repeated amount
        $this->totals['due'] = 5.05;
        $details = ['amount' => 15.00, 'description' => ''];
        $this->assertFalse($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));
        $this->assertEqualsWithDelta(5.05, $this->totals['due'], 0.001);
    }

    public function testKeyIsNotKeptWhenInvoiceCannotBeCreated(): void
    {
        $instance = $this->getInstanceWith('trx-9', 20.05, 0);
        $details = ['amount' => 20.05, 'description' => ''];
        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));
        $this->assertSame(['trx-9'], $this->additionalInformation['buckaroo_applied_transfer_transactions']);

        // simulate the failed invoice: the key must be released again so a retry can invoice
        $parent = new \ReflectionMethod($instance, 'forgetAppliedTransactionKey');
        $parent->setAccessible(true);
        $parent->invoke($instance);
        $this->assertSame([], $this->additionalInformation['buckaroo_applied_transfer_transactions']);

        $details = ['amount' => 20.05, 'description' => ''];
        $this->assertTrue($this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance));
    }

    public function testPaymentWithoutTransactionKeyIsStillApplied(): void
    {
        $instance = $this->getInstanceWith('', 10.00, 2);
        $details = ['amount' => 10.00, 'description' => ''];
        $this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance);
        $details = ['amount' => 10.00, 'description' => ''];
        $this->invokeArgs('invoiceShouldBeSaved', [&$details], $instance);

        $this->assertEqualsWithDelta(0.05, $this->totals['due'], 0.001);
    }

    private function createRequest(string $key, float $amount): PushRequestInterface
    {
        $request = $this->getFakeMock(PushRequestInterface::class)->getMock();
        $request->method('getTransactions')->willReturn($key);
        $request->method('getAmount')->willReturn((string)$amount);

        return $request;
    }

    /**
     * Order owes 20.05 and nothing is paid yet; invoicing happens on shipment.
     */
    private function getInstanceWith(string $key, float $amount, int $saves)
    {
        $this->totals = ['due' => 20.05, 'baseDue' => 20.05, 'paid' => 0.0, 'basePaid' => 0.0];
        $this->additionalInformation = [];

        $methodInstance = $this->getFakeMock(MethodInterface::class)->getMock();
        $methodInstance->method('getConfigData')->willReturn(null);

        $payment = $this->getFakeMock(Payment::class)->getMock();
        $payment->method('getMethodInstance')->willReturn($methodInstance);
        $payment->method('getAdditionalInformation')->willReturnCallback(function ($k = null) {
            return $this->additionalInformation[$k] ?? null;
        });
        $payment->method('setAdditionalInformation')->willReturnCallback(function ($k, $v = null) use ($payment) {
            $this->additionalInformation[$k] = $v;
            return $payment;
        });

        $order = $this->getFakeMock(Order::class)->getMock();
        $order->method('getId')->willReturn(1);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getStatus')->willReturn('pending');
        $order->method('getGrandTotal')->willReturn(20.05);
        $order->method('getBaseGrandTotal')->willReturn(20.05);
        $order->method('getTotalDue')->willReturnCallback(function () {
            return $this->totals['due'];
        });
        $order->method('getBaseTotalDue')->willReturnCallback(function () {
            return $this->totals['baseDue'];
        });
        $order->method('getTotalPaid')->willReturnCallback(function () {
            return $this->totals['paid'];
        });
        $order->method('getBaseTotalPaid')->willReturnCallback(function () {
            return $this->totals['basePaid'];
        });
        foreach (['TotalDue' => 'due', 'BaseTotalDue' => 'baseDue', 'TotalPaid' => 'paid',
                     'BaseTotalPaid' => 'basePaid'] as $method => $field) {
            $order->method('set' . $method)->willReturnCallback(function ($v) use ($field, $order) {
                $this->totals[$field] = $v;
                return $order;
            });
        }

        $orderRequestService = $this->getFakeMock(OrderRequestService::class)->getMock();
        $orderRequestService->expects($this->exactly($saves))->method('saveAndReloadOrder');

        $statusFactory = $this->getFakeMock(\Buckaroo\Magento2\Model\OrderStatusFactory::class)->getMock();
        $statusFactory->method('get')->willReturn('pending');

        $configAccount = $this->getFakeMock(Account::class)->getMock();
        $configAccount->method('getInvoiceHandling')->willReturn(InvoiceHandlingOptions::SHIPMENT);

        $instance = $this->getInstance([
            'orderRequestService' => $orderRequestService,
            'configAccount'       => $configAccount,
            'orderStatusFactory'  => $statusFactory,
        ]);

        $this->setProperty('order', $order, $instance);
        $this->setProperty('payment', $payment, $instance);
        $this->setProperty('pushRequest', $this->createRequest($key, $amount), $instance);

        return $instance;
    }
}
