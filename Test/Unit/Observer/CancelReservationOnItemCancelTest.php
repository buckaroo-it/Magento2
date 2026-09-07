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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;

/**
 * A fulfilment system cancels the lines it could not ship instead of cancelling the
 * order, which never reaches order_cancel_after. The uncaptured part of the Klarna reservation
 * must still be released, but only once no line can be captured any more.
 */
class CancelReservationOnItemCancelTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Observer\CancelReservationOnItemCancel';

    public function testTheReservationIsCancelledWhenTheLastOpenLineIsCancelled(): void
    {
        $shippedItem = $this->makeItem(1, 0.0, 0.0);
        $cancelledItem = $this->makeItem(2, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->once(), true),
        ]);

        $instance->execute($this->makeObserver($cancelledItem, [$shippedItem, $cancelledItem], true));
    }

    public function testTheReservationStaysOpenWhileOtherLinesCanStillBeCaptured(): void
    {
        $openItem = $this->makeItem(1, 3.0, 3.0);
        $cancelledItem = $this->makeItem(2, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute($this->makeObserver($cancelledItem, [$openItem, $cancelledItem], true));
    }

    public function testAnOrderWithoutInvoicesIsLeftToTheRegularCancelPath(): void
    {
        $cancelledItem = $this->makeItem(1, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute($this->makeObserver($cancelledItem, [$cancelledItem], false));
    }

    public function testNonKlarnaOrdersAreIgnored(): void
    {
        $cancelledItem = $this->makeItem(1, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute(
            $this->makeObserver($cancelledItem, [$cancelledItem], true, 'buckaroo_magento2_ideal')
        );
    }

    /**
     * Cancelling one line of two open lines must leave the reservation alone; cancelling the
     * last one releases it.
     */
    public function testTheReservationIsOnlyReleasedByTheLastCancellation(): void
    {
        $firstToCancel = $this->makeItem(1, 2.0, 2.0);
        $lastToCancel = $this->makeItem(2, 3.0, 3.0);

        $stillOpen = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);
        $stillOpen->execute($this->makeObserver($firstToCancel, [$firstToCancel, $lastToCancel], true));

        // Magento has written qty_canceled on the first item by the time the second fires.
        $alreadyCancelled = $this->makeItem(1, 0.0, 0.0);
        $closing = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->once(), true),
        ]);
        $closing->execute($this->makeObserver($lastToCancel, [$alreadyCancelled, $lastToCancel], true));
    }

    /**
     * A part-cancelled line still has quantity that can be captured.
     */
    public function testAPartialCancellationOfASingleLineKeepsTheReservation(): void
    {
        $item = $this->makeItem(1, 5.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute($this->makeObserver($item, [$item], true));
    }

    /**
     * Bundle parents and other dummy rows carry no capturable value.
     */
    public function testDummyItemsDoNotKeepTheReservationOpen(): void
    {
        $dummy = $this->makeItem(1, 4.0, 0.0, true);
        $cancelledItem = $this->makeItem(2, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->once(), true),
        ]);

        $instance->execute($this->makeObserver($cancelledItem, [$dummy, $cancelledItem], true));
    }

    /**
     * Klarna MoR holds the same kind of reservation.
     */
    public function testKlarnaMorOrdersAreHandledToo(): void
    {
        $cancelledItem = $this->makeItem(1, 2.0, 2.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->once(), true),
        ]);

        $instance->execute(
            $this->makeObserver($cancelledItem, [$cancelledItem], true, Klarna::CODE)
        );
    }

    /**
     * Order::cancel() dispatches this event for a configurable's child row too. Order 300000019
     * ran the whole decision four times for two products because of it.
     */
    public function testADummyCancelledItemIsSkipped(): void
    {
        $dummyChild = $this->makeItem(1, 0.0, 0.0, true);
        $openItem = $this->makeItem(2, 1.0, 0.0);

        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute($this->makeObserver($dummyChild, [$dummyChild, $openItem], true));
    }

    public function testAnEventWithoutAnItemIsIgnored(): void
    {
        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $instance->execute($this->makeObserverFor(null));
    }

    public function testAnItemWithoutAnOrderIsIgnored(): void
    {
        $instance = $this->getInstance([
            'cancelRemainingReservation' => $this->makeCancelServiceExpecting($this->never(), true),
        ]);

        $item = $this->makeItem(1, 2.0, 2.0);
        $item->method('getOrder')->willReturn(null);

        $instance->execute($this->makeObserverFor($item));
    }

    /**
     * @param int   $id
     * @param float $qtyToInvoice
     * @param float $qtyToCancel
     * @param bool  $isDummy
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeItem(int $id, float $qtyToInvoice, float $qtyToCancel, bool $isDummy = false)
    {
        $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $item->method('getId')->willReturn($id);
        $item->method('isDummy')->willReturn($isDummy);
        $item->method('getQtyToInvoice')->willReturn($qtyToInvoice);
        $item->method('getQtyToCancel')->willReturn($qtyToCancel);
        $item->method('getSku')->willReturn('SKU' . $id);

        return $item;
    }

    /**
     * @param mixed $expectation
     * @param bool  $result
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeCancelServiceExpecting($expectation, bool $result)
    {
        $service = $this->getFakeMock('Buckaroo\Magento2\Model\Service\Order\CancelRemainingReservation')
            ->getMock();
        $service->expects($expectation)->method('execute')->willReturn($result);

        return $service;
    }

    /**
     * @param object $cancelledItem
     * @param array  $orderItems
     * @param bool   $hasInvoices
     * @param string $methodCode
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeObserver($cancelledItem, array $orderItems, bool $hasInvoices, ?string $methodCode = null)
    {
        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethod')->willReturn($methodCode ?? Klarnakp::CODE);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getAllItems')->willReturn($orderItems);
        $order->method('getIncrementId')->willReturn('000000123');

        $cancelledItem->method('getOrder')->willReturn($order);

        return $this->makeObserverFor($cancelledItem);
    }

    /**
     * Magento\Framework\Event resolves getItem() through __call, so a real instance carries the
     * payload without needing the mock builder's addMethods() - removed in PHPUnit 12.
     *
     * @param object|null $item
     *
     * @return \Magento\Framework\Event\Observer
     */
    private function makeObserverFor($item)
    {
        return new \Magento\Framework\Event\Observer(
            ['event' => new \Magento\Framework\Event(['item' => $item])]
        );
    }
}
