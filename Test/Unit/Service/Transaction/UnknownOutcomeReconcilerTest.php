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

namespace Buckaroo\Magento2\Test\Unit\Service\Transaction;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Buckaroo\Magento2\Service\Transaction\UnknownOutcomeReconciler;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UnknownOutcomeReconcilerTest extends TestCase
{
    private const NOW = 1800000000;

    /**
     * @var BuckarooAdapter|MockObject
     */
    private $adapter;

    /**
     * @var CancelGuard|MockObject
     */
    private $cancelGuard;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(BuckarooAdapter::class);
        $this->cancelGuard = $this->createMock(CancelGuard::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
    }

    public static function cancelProvider(): array
    {
        return [
            'only failed transactions'       => [[490], 15, true],
            'failed and cancelled'           => [[490, 890], 15, true],
            'nothing after 30 minutes'       => [[], 31, true],
            'nothing yet (20 minutes)'       => [[], 20, false],
            'still pending'                  => [[791], 60, false],
            'failed attempt and pending one' => [[490, 791], 60, false],
            'paid'                           => [[190], 60, false],
            'too early to look'              => [[490], 5, false],
        ];
    }

    #[DataProvider('cancelProvider')]
    public function testCancelsOnlyWhenBuckarooHasNoSuccessfulOrPendingPayment(
        array $statuses,
        int $minutesWaiting,
        bool $expectCancel
    ): void {
        $order = $this->order($minutesWaiting);
        $this->givenStatuses($statuses);

        $this->cancelGuard->expects($expectCancel ? $this->once() : $this->never())->method('runLocked');

        $this->reconciler()->reconcile($order, self::NOW);
    }

    public function testCancellingClearsTheFlagAndCancelsTheFreshOrder(): void
    {
        $this->givenStatuses([490]);
        $fresh = $this->order(15);
        $fresh->getPayment()->expects($this->once())->method('unsAdditionalInformation')
            ->with(CancelGuard::OUTCOME_UNKNOWN);
        $fresh->expects($this->once())->method('cancel');
        $this->orderRepository->expects($this->once())->method('save')->with($fresh);
        $this->cancelGuard->method('getCancelBlocker')->willReturn(null);
        $this->cancelGuard->method('runLocked')->willReturnCallback(fn(...$args) => $args[2]($fresh));

        $this->reconciler()->reconcile($this->order(15), self::NOW);
    }

    public function testAPaidOrderWithoutAPushIsReportedOnce(): void
    {
        $this->givenStatuses([190]);
        $order = $this->order(150);
        $order->expects($this->once())->method('addCommentToStatusHistory');
        $order->getPayment()->expects($this->once())->method('setAdditionalInformation')
            ->with(UnknownOutcomeReconciler::NOTED_PAID_WITHOUT_PUSH, 1);
        $this->cancelGuard->expects($this->never())->method('runLocked');

        $this->reconciler()->reconcile($order, self::NOW);

        $noted = $this->order(150, [UnknownOutcomeReconciler::NOTED_PAID_WITHOUT_PUSH => 1]);
        $noted->expects($this->never())->method('addCommentToStatusHistory');
        $this->reconciler()->reconcile($noted, self::NOW);
    }

    public function testOrdersWithoutTheFlagAreLeftAlone(): void
    {
        $this->adapter->expects($this->never())->method('getTransactionsByInvoice');

        $this->reconciler()->reconcile($this->order(60, [CancelGuard::OUTCOME_UNKNOWN => null]), self::NOW);
    }

    /**
     * @param int[] $statuses
     */
    private function givenStatuses(array $statuses): void
    {
        $this->adapter->method('getTransactionsByInvoice')->willReturn(array_map(
            fn($status, $i) => ['key' => 'KEY' . $i, 'status' => $status],
            $statuses,
            array_keys($statuses)
        ));
    }

    /**
     * @param int   $minutesWaiting
     * @param array $info
     *
     * @return Order|MockObject
     */
    private function order(int $minutesWaiting, array $info = [])
    {
        $info += [CancelGuard::OUTCOME_UNKNOWN => self::NOW - $minutesWaiting * 60];

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('buckaroo_magento2_applepay');
        $payment->method('getAdditionalInformation')->willReturnCallback(fn($key) => $info[$key] ?? null);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(9);
        $order->method('getIncrementId')->willReturn('300000524');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getPayment')->willReturn($payment);

        return $order;
    }

    /**
     * @return UnknownOutcomeReconciler
     */
    private function reconciler(): UnknownOutcomeReconciler
    {
        return new UnknownOutcomeReconciler(
            $this->createMock(CollectionFactory::class),
            $this->adapter,
            $this->cancelGuard,
            $this->orderRepository,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }
}
