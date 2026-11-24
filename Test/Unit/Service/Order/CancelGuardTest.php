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

namespace Buckaroo\Magento2\Test\Unit\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CancelGuardTest extends TestCase
{
    /**
     * @var LockManagerWrapper|MockObject
     */
    private $lockManager;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    protected function setUp(): void
    {
        $this->lockManager = $this->createMock(LockManagerWrapper::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
    }

    public static function blockerProvider(): array
    {
        return [
            'invoiced'          => [['invoices' => true], 'invoiced'],
            'paid'              => [['paid' => 40.05], 'paid'],
            'processing'        => [['state' => Order::STATE_PROCESSING], 'processing'],
            'already cancelled' => [['state' => Order::STATE_CANCELED], 'canceled'],
            'outcome unknown'   => [['unknown' => true], 'Buckaroo may have processed'],
            'not cancellable'   => [['canCancel' => false], 'does not allow'],
        ];
    }

    #[DataProvider('blockerProvider')]
    public function testDoesNotCancelAnOrderThatIsOrMayBePaid(array $overrides, string $reason): void
    {
        $order = $this->order($overrides);
        $order->expects($this->never())->method('cancel');
        $this->lockManager->method('lockOrder')->willReturn(true);

        $this->assertStringContainsString($reason, (string)$this->guard([$order, $order])->getCancelBlocker($order));
        $this->assertFalse($this->guard([$order, $order])->cancel(7, 'test'));
    }

    public function testCancelsTheFreshCopyUnderTheOrderLock(): void
    {
        $stale = $this->order([]);
        $stale->expects($this->never())->method('cancel');
        $fresh = $this->order([]);
        $fresh->expects($this->once())->method('cancel');

        $this->lockManager->expects($this->once())->method('lockOrder')->with('300000493', 5)->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlockOrder')->with('300000493');
        $this->orderRepository->expects($this->once())->method('save')->with($fresh);

        $seen = null;
        $cancelled = $this->guard([$stale, $fresh])->cancel(7, 'test', function (Order $order) use (&$seen) {
            $seen = $order;
        });

        $this->assertTrue($cancelled);
        $this->assertSame($fresh, $seen);
    }

    public function testAPushThatPaidTheOrderWhileWaitingForTheLockWins(): void
    {
        $before = $this->order([]);
        $afterPush = $this->order(['invoices' => true, 'paid' => 40.05, 'state' => Order::STATE_PROCESSING]);
        $afterPush->expects($this->never())->method('cancel');
        $this->lockManager->method('lockOrder')->willReturn(true);

        $this->assertFalse($this->guard([$before, $afterPush])->cancel(7, 'test'));
    }

    public function testNothingHappensWithoutTheLock(): void
    {
        $order = $this->order([]);
        $order->expects($this->never())->method('cancel');
        $this->lockManager->method('lockOrder')->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlockOrder');

        $this->assertFalse($this->guard([$order])->cancel(7, 'test'));
    }

    /**
     * @param Order[] $loads one order per load, in order
     *
     * @return CancelGuard
     */
    private function guard(array $loads): CancelGuard
    {
        $factory = $this->createMock(OrderFactory::class);
        $models = [];
        foreach ($loads as $order) {
            $model = $this->createMock(Order::class);
            $model->method('load')->willReturn($order);
            $models[] = $model;
        }
        $factory->method('create')->willReturnOnConsecutiveCalls(...$models);

        return new CancelGuard(
            $this->lockManager,
            $factory,
            $this->orderRepository,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    /**
     * @param array $overrides
     *
     * @return Order|MockObject
     */
    private function order(array $overrides)
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn($key) => $key === CancelGuard::OUTCOME_UNKNOWN && !empty($overrides['unknown']) ? 1 : null
        );

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(7);
        $order->method('getIncrementId')->willReturn('300000493');
        $order->method('hasInvoices')->willReturn($overrides['invoices'] ?? false);
        $order->method('getTotalPaid')->willReturn($overrides['paid'] ?? null);
        $order->method('getState')->willReturn($overrides['state'] ?? Order::STATE_NEW);
        $order->method('canCancel')->willReturn($overrides['canCancel'] ?? true);
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }
}
