<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Buckaroo\Magento2\Observer\CreateSecondChanceRecord;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as ConfigProvider;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\Store;
use Magento\Framework\Exception\NoSuchEntityException;

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
        $this->configProvider = $this->getFakeMock(ConfigProvider::class)->getMock();
        $this->logging = $this->getFakeMock(Log::class)->getMock();
    }

    /**
     * @return array
     */
    public function executeDataProvider()
    {
        return [
            'valid buckaroo order pending_payment' => [
                'order_id' => 123,
                'state' => 'pending_payment',
                'payment_method' => 'buckaroo_magento2_ideal',
                'second_chance_enabled' => true,
                'record_exists' => false,
                'expected_create_calls' => 1,
                'expected_debug_calls' => 1
            ],
            'valid buckaroo order canceled' => [
                'order_id' => 124,
                'state' => 'canceled',
                'payment_method' => 'buckaroo_magento2_paypal',
                'second_chance_enabled' => true,
                'record_exists' => false,
                'expected_create_calls' => 1,
                'expected_debug_calls' => 1
            ],
            'order with non-buckaroo payment method' => [
                'order_id' => 125,
                'state' => 'pending_payment',
                'payment_method' => 'checkmo',
                'second_chance_enabled' => true,
                'record_exists' => false,
                'expected_create_calls' => 0,
                'expected_debug_calls' => 0
            ],
            'order with completed state' => [
                'order_id' => 126,
                'state' => 'complete',
                'payment_method' => 'buckaroo_magento2_ideal',
                'second_chance_enabled' => true,
                'record_exists' => false,
                'expected_create_calls' => 0,
                'expected_debug_calls' => 0
            ],
            'second chance disabled' => [
                'order_id' => 127,
                'state' => 'pending_payment',
                'payment_method' => 'buckaroo_magento2_ideal',
                'second_chance_enabled' => false,
                'record_exists' => false,
                'expected_create_calls' => 0,
                'expected_debug_calls' => 0
            ],
            'record already exists' => [
                'order_id' => 128,
                'state' => 'pending_payment',
                'payment_method' => 'buckaroo_magento2_ideal',
                'second_chance_enabled' => true,
                'record_exists' => true,
                'expected_create_calls' => 0,
                'expected_debug_calls' => 0
            ]
        ];
    }

    /**
     * @param int $orderId
     * @param string $state
     * @param string $paymentMethod
     * @param bool $secondChanceEnabled
     * @param bool $recordExists
     * @param int $expectedCreateCalls
     * @param int $expectedDebugCalls
     * 
     * @dataProvider executeDataProvider
     */
    public function testExecute($orderId, $state, $paymentMethod, $secondChanceEnabled, $recordExists, $expectedCreateCalls, $expectedDebugCalls)
    {
        $observer = $this->getFakeMock(Observer::class)->getMock();
        $event = $this->getFakeMock(Event::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $observer->expects($this->once())->method('getEvent')->willReturn($event);
        $event->expects($this->once())->method('getOrder')->willReturn($order);
        
        $order->expects($this->once())->method('getId')->willReturn($orderId);
        $order->expects($this->atLeastOnce())->method('getState')->willReturn($state);
        
        if (in_array($state, ['pending_payment', 'canceled'])) {
            $order->expects($this->once())->method('getStore')->willReturn($store);
            
            $this->configProvider->expects($this->once())
                ->method('isSecondChanceEnabled')
                ->with($store)
                ->willReturn($secondChanceEnabled);
                
            if ($secondChanceEnabled) {
                $order->expects($this->once())->method('getPayment')->willReturn($payment);
                $payment->expects($this->once())->method('getMethod')->willReturn($paymentMethod);
                
                if (strpos($paymentMethod, 'buckaroo') !== false) {
                    $order->expects($this->once())->method('getIncrementId')->willReturn('000000' . $orderId);
                    
                    if ($recordExists) {
                        $this->secondChanceRepository->expects($this->once())
                            ->method('getByOrderId')
                            ->with('000000' . $orderId)
                            ->willReturn($this->getFakeMock(\Buckaroo\Magento2\Api\Data\SecondChanceInterface::class)->getMock());
                    } else {
                        $this->secondChanceRepository->expects($this->once())
                            ->method('getByOrderId')
                            ->with('000000' . $orderId)
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
            'configProvider' => $this->configProvider,
            'logging' => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithNullOrder()
    {
        $observer = $this->getFakeMock(Observer::class)->getMock();
        $event = $this->getFakeMock(Event::class)->getMock();
        
        $observer->expects($this->once())->method('getEvent')->willReturn($event);
        $event->expects($this->once())->method('getOrder')->willReturn(null);
        
        $this->configProvider->expects($this->never())->method('isSecondChanceEnabled');
        $this->secondChanceRepository->expects($this->never())->method('createSecondChance');

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider' => $this->configProvider,
            'logging' => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithOrderWithoutId()
    {
        $observer = $this->getFakeMock(Observer::class)->getMock();
        $event = $this->getFakeMock(Event::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        
        $observer->expects($this->once())->method('getEvent')->willReturn($event);
        $event->expects($this->once())->method('getOrder')->willReturn($order);
        $order->expects($this->once())->method('getId')->willReturn(null);
        
        $this->configProvider->expects($this->never())->method('isSecondChanceEnabled');
        $this->secondChanceRepository->expects($this->never())->method('createSecondChance');

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider' => $this->configProvider,
            'logging' => $this->logging,
        ]);

        $instance->execute($observer);
    }

    public function testExecuteWithException()
    {
        $observer = $this->getFakeMock(Observer::class)->getMock();
        $event = $this->getFakeMock(Event::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $observer->expects($this->once())->method('getEvent')->willReturn($event);
        $event->expects($this->once())->method('getOrder')->willReturn($order);
        
        $order->expects($this->once())->method('getId')->willReturn(123);
        $order->expects($this->once())->method('getState')->willReturn('pending_payment');
        $order->expects($this->once())->method('getStore')->willReturn($store);
        $order->expects($this->once())->method('getPayment')->willReturn($payment);
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000123');
        
        $this->configProvider->expects($this->once())
            ->method('isSecondChanceEnabled')
            ->with($store)
            ->willReturn(true);
            
        $payment->expects($this->once())->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        
        $this->secondChanceRepository->expects($this->once())
            ->method('getByOrderId')
            ->with('000000123')
            ->willThrowException(new NoSuchEntityException(__('Record not found')));
            
        $this->secondChanceRepository->expects($this->once())
            ->method('createSecondChance')
            ->with($order)
            ->willThrowException(new \Exception('Database error'));
        
        $this->logging->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Error creating SecondChance record'));

        $instance = $this->getInstance([
            'secondChanceRepository' => $this->secondChanceRepository,
            'configProvider' => $this->configProvider,
            'logging' => $this->logging,
        ]);

        $instance->execute($observer);
    }
} 