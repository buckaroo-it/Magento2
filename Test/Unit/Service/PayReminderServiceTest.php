<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Service;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Sales\Model\Order;

class PayReminderServiceTest extends BaseTest
{
    protected $instanceClass = PayReminderService::class;

    private $paymentGroupTransactionMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentGroupTransactionMock = $this->createMock(PaymentGroupTransaction::class);
    }

    public function getInstance(array $args = [])
    {
        return parent::getInstance($args + [
            'paymentGroupTransaction' => $this->paymentGroupTransactionMock,
        ]);
    }

    /**
     * The service is a shared instance: state cached for one order must never
     * leak into a different order handled in the same request (PayLink batches,
     * mass refunds, multi-order API calls).
     */
    public function testStateIsKeyedPerOrderAndDoesNotLeakBetweenOrders(): void
    {
        $instance = $this->getInstance();

        $this->paymentGroupTransactionMock->method('getAlreadyPaid')->willReturnMap([
            ['100000001', 30.0],
            ['100000002', 0.0],
        ]);

        $orderA = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $orderA->setData(['increment_id' => '100000001', 'grand_total' => 100.0]);

        $orderB = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $orderB->setData(['increment_id' => '100000002', 'grand_total' => 50.0]);

        // Order A: 30 already paid via giftcards
        $this->assertEquals(30.0, $instance->getAlreadyPaid('100000001'));
        $this->assertTrue($instance->isPayRemainder($orderA));
        $this->assertEquals(70.0, $instance->getPayRemainder($orderA));
        $this->assertSame('payRemainder', $instance->getServiceAction('100000001'));

        // Order B in the same request: nothing paid - must not inherit A's state
        $this->assertEquals(0.0, $instance->getAlreadyPaid('100000002'), 'alreadyPaid leaked between orders');
        $this->assertFalse($instance->isPayRemainder($orderB), 'payRemainder flag leaked between orders');
        $this->assertEquals(0.0, $instance->getPayRemainder($orderB), 'remainder leaked between orders');
        $this->assertSame('pay', $instance->getServiceAction('100000002'), 'serviceAction leaked between orders');
    }

    /**
     * getPayRemainder() is declared to return float and must not return the
     * uninitialised null when nothing was paid (TypeError under strict_types).
     */
    public function testGetPayRemainderReturnsZeroFloatWhenNothingPaid(): void
    {
        $instance = $this->getInstance();

        $this->paymentGroupTransactionMock->method('getAlreadyPaid')->willReturn(0.0);

        $order = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $order->setData(['increment_id' => '100000003', 'grand_total' => 25.0]);

        $this->assertSame(0.0, $instance->getPayRemainder($order));
    }
}
