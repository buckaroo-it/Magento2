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

namespace Buckaroo\Magento2\Test\Unit\Observer;

use PHPUnit\Framework\Attributes\DataProvider;
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
     * Rows: state, payment method, second chance enabled, record exists, expected createSecondChance calls.
     *
     * @return array
     */
    public static function executeDataProvider()
    {
        return [
            'pending_payment order is recorded'   => ['pending_payment', 'buckaroo_magento2_ideal', true, false, 1],
            'canceled order is recorded'          => ['canceled', 'buckaroo_magento2_paypal', true, false, 1],
            'second chance disabled'              => ['pending_payment', 'buckaroo_magento2_ideal', false, false, 0],
            'complete order is not recorded'      => ['complete', 'buckaroo_magento2_ideal', true, false, 0],
            'processing order is not recorded'    => ['processing', 'buckaroo_magento2_ideal', true, false, 0],
            'transfer payment is excluded'        => ['pending_payment', 'buckaroo_magento2_transfer', true, false, 0],
            'non-buckaroo payment method'         => ['pending_payment', 'checkmo', true, false, 0],
            'record already exists'               => ['pending_payment', 'buckaroo_magento2_ideal', true, true, 0],
        ];
    }

    /**
     * Every collaborator is configured for every row, so each row is decided by its own condition only.
     *
     * @param string $state
     * @param string $paymentMethod
     * @param bool   $secondChanceEnabled
     * @param bool   $recordExists
     * @param int    $expectedCreateCalls
     */
    #[DataProvider('executeDataProvider')]
    public function testExecute(
        string $state,
        string $paymentMethod,
        bool $secondChanceEnabled,
        bool $recordExists,
        int $expectedCreateCalls
    ): void {
        $observer = new Observer();
        $event    = new Event();
        $observer->setData('event', $event);

        $order   = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $store   = $this->getFakeMock(Store::class)->getMock();

        $event->setData('order', $order);

        $order->method('getId')->willReturn(123);
        $order->method('getState')->willReturn($state);
        $order->method('getStore')->willReturn($store);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getIncrementId')->willReturn('000000123');
        $payment->method('getMethod')->willReturn($paymentMethod);

        $this->configProvider->method('isSecondChanceEnabled')
            ->willReturnCallback(function ($givenStore) use ($store, $secondChanceEnabled) {
                $this->assertSame($store, $givenStore);
                return $secondChanceEnabled;
            });

        $lookup = $this->secondChanceRepository->method('getByOrderId')->with('000000123');
        if ($recordExists) {
            $lookup->willReturn(
                $this->getFakeMock(\Buckaroo\Magento2\Api\Data\SecondChanceInterface::class)->getMock()
            );
        } else {
            $lookup->willThrowException(new NoSuchEntityException(__('Record not found')));
        }

        $this->secondChanceRepository->expects($this->exactly($expectedCreateCalls))
            ->method('createSecondChance')
            ->with($order);

        $this->logging->expects($this->exactly($expectedCreateCalls))
            ->method('addDebug')
            ->with('SecondChance record created for order: 000000123');

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

        // The repository failure is logged, not thrown.
        $instance->execute($observer);
    }
}
