<?php
/**
 * NOTICE OF LICENSE
 *
 * (header omitted)
 */

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Observer\CreateSecondChanceRecord;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as ConfigProvider;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\Store;

class CreateSecondChanceRecordTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = CreateSecondChanceRecord::class;

    /** @var SecondChanceRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $secondChanceRepository;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $configProvider;

    /** @var Log|\PHPUnit\Framework\MockObject\MockObject */
    private $logging;

    public function setUp(): void
    {
        parent::setUp();

        $this->secondChanceRepository = $this->getFakeMock(SecondChanceRepository::class)->getMock();
        $this->configProvider         = $this->getFakeMock(ConfigProvider::class)->getMock();
        $this->logging                = $this->getFakeMock(Log::class)->getMock();
    }

    /**
     * @return array
     */
    public static function executeDataProvider()
    {
        return [
            'valid buckaroo order pending_payment' => [
                123, 'pending_payment', 'buckaroo_magento2_ideal', true,  false, 1, 1
            ],
            'valid buckaroo order canceled' => [
                124, 'canceled', 'buckaroo_magento2_paypal', true,  false, 1, 1
            ],
            'order with non-buckaroo payment method' => [
                125, 'pending_payment', 'checkmo', true,  false, 0, 0
            ],
            'order with completed state' => [
                126, 'complete', 'buckaroo_magento2_ideal', true,  false, 0, 0
            ],
            'second chance disabled' => [
                127, 'pending_payment', 'buckaroo_magento2_ideal', false, false, 0, 0
            ],
            'record already exists' => [
                128, 'pending_payment', 'buckaroo_magento2_ideal', true,  true,  0, 0
            ],
        ];
    }

    /**
     * @dataProvider executeDataProvider
     * @param int    $orderId
     * @param string $state
     * @param string $paymentMethod
     * @param bool   $secondChanceEnabled
     * @param bool   $recordExists
     * @param int    $expectedCreateCalls
     * @param int    $expectedDebugCalls
     */
    public function testExecute(
        int $orderId,
        string $state,
        string $paymentMethod,
        bool $secondChanceEnabled,
        bool $recordExists,
        int $expectedCreateCalls,
        int $expectedDebugCalls
    ): void {
        // Use REAL Observer/Event objects; do not mock magic getters.
        $observer = new Observer();
        $event    = new Event();
        // Attach the event to the observer
        $observer->setData('event', $event);

        // Usual collaborators as mocks
        $order   = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $store   = $this->getFakeMock(Store::class)->getMock();

        // Put the order into the event payload
        $event->setData('order', $order);

        // Order basics
        $order->method('getId')->willReturn($orderId);
        $order->expects($this->atLeastOnce())->method('getState')->willReturn($state);

        if (in_array($state, ['pending_payment', 'canceled'], true)) {
            $order->method('getStore')->willReturn($store);

            $this->configProvider->method('isSecondChanceEnabled')
                ->with($store)
                ->willReturn($secondChanceEnabled);

            if ($secondChanceEnabled) {
                $order->method('getPayment')->willReturn($payment);
                $payment->method('getMethod')->willReturn($paymentMethod);

                if (strpos($paymentMethod, 'buckaroo') !== false) {
                    $incrementId = sprintf('%06d', $orderId);
                    $order->method('getIncrementId')->willReturn($incrementId);

                    if ($recordExists) {
                        $this->secondChanceRepository->method('getByOrderId')
                            ->with($incrementId)
                            ->willReturn(
                                $this->getFakeMock(\Buckaroo\Magento2\Api\Data\SecondChanceInterface::class)->getMock()
                            );
                    } else {
                        $this->secondChanceRepository->method('getByOrderId')
                            ->with($incrementId)
                            ->willThrowException(new NoSuchEntityException(__('Record not found')));
                    }
                }
            }
        }

        $this->secondChanceRepository->expects($this->exactly($expectedCreateCalls))
            ->method('createSecondChance')
            ->with($order);

        $this->logging->expects($this->exactly($expectedDebugCalls))
            ->method('addDebug')
            ->with($this->stringContains('SecondChance record created for order'));

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider'         => $this->configProvider,
            'logging'                => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithNullOrder(): void
    {
        $observer = new Observer();
        $event    = new Event();
        $observer->setData('event', $event);

        // No order in event
        $event->setData('order', null);

        $this->configProvider->expects($this->never())->method('isSecondChanceEnabled');
        $this->secondChanceRepository->expects($this->never())->method('createSecondChance');

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider'         => $this->configProvider,
            'logging'                => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithOrderWithoutId(): void
    {
        $observer = new Observer();
        $event    = new Event();
        $observer->setData('event', $event);

        $order = $this->getFakeMock(Order::class)->getMock();
        $event->setData('order', $order);

        $order->method('getId')->willReturn(null);

        $this->configProvider->expects($this->never())->method('isSecondChanceEnabled');
        $this->secondChanceRepository->expects($this->never())->method('createSecondChance');

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider'         => $this->configProvider,
            'logging'                => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithException(): void
    {
        $observer = new Observer();
        $event    = new Event();
        $observer->setData('event', $event);

        $order   = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $store   = $this->getFakeMock(Store::class)->getMock();

        $event->setData('order', $order);

        $order->method('getId')->willReturn(123);
        $order->method('getState')->willReturn('pending_payment');
        $order->method('getStore')->willReturn($store);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getIncrementId')->willReturn('000000123');

        $this->configProvider->method('isSecondChanceEnabled')
            ->with($store)
            ->willReturn(true);

        $payment->method('getMethod')->willReturn('buckaroo_magento2_ideal');

        $this->secondChanceRepository->method('getByOrderId')
            ->with('000000123')
            ->willThrowException(new NoSuchEntityException(__('Record not found')));

        $this->secondChanceRepository->method('createSecondChance')
            ->with($order)
            ->willThrowException(new \Exception('Database error'));

        // Add expectations for error logging
        $this->logging->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Error creating SecondChance record'));

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider'         => $this->configProvider,
            'logging'                => $this->logging,
        ]);

        // Test that the method executes without throwing exception even when repository fails
        $instance->execute($observer);

        // Add assertion to prevent risky test
        $this->assertTrue(true, 'Observer executed successfully despite repository exception');
    }
}
