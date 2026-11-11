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

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;

/**
 * A customer pays part of an order with a gift card and leaves the payment page with the browser
 * back button. The part is refunded. The refund push used to create an invoice for the whole order
 * and a credit memo for the part, leaving an unpaid order in Processing with nothing due.
 */
class RefundOfUnpaidPartialPaymentTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\RefundProcessor';

    /**
     * Transaction key of the refunded gift card part.
     */
    private const PART = '9898363B3646D5208B3728175261FA92';

    public function testARefundedGiftCardPartOnAnUnpaidOrderIsRecognised(): void
    {
        $instance = $this->makeProcessor($this->makeOrder(false, 0.0), $this->makePart('300000376'));

        $this->assertTrue($this->invoke('isRefundOfUnpaidPartialPayment', $instance));
    }

    /**
     * @param bool   $hasInvoices
     * @param float  $totalPaid
     * @param string $partOrder
     * @param bool   $partExists
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('otherRefundProvider')]
    public function testOtherRefundsKeepTheCreditMemoFlow(
        bool $hasInvoices,
        float $totalPaid,
        string $partOrder,
        bool $partExists
    ): void {
        $part = $partExists ? $this->makePart($partOrder) : new DataObject();
        $instance = $this->makeProcessor($this->makeOrder($hasInvoices, $totalPaid), $part);

        $this->assertFalse($this->invoke('isRefundOfUnpaidPartialPayment', $instance));
    }

    public static function otherRefundProvider(): array
    {
        return [
            'invoiced order (gift card part refunded after a full payment)' => [true, 40.05, '300000376', true],
            'captured, not invoiced yet (Riverty / Klarna on shipment)' => [false, 0.0, '300000376', false],
            'something already paid' => [false, 10.00, '300000376', true],
            'part of another order' => [false, 0.0, '300000999', true],
        ];
    }

    public function testTheOrderIsCancelledWithoutInvoiceOrCreditMemo(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->never())->method('addCommentToStatusHistory');

        $reloaded = $this->getFakeMock(Order::class)->getMock();
        $reloaded->expects($this->once())->method('addCommentToStatusHistory')
            ->with($this->stringContains('refunded before the order was paid'));

        $groupTransaction = $this->makeGroupTransaction($this->makePart('300000376'), 0.0);
        $groupTransaction->expects($this->once())->method('updateGroupTransaction')
            ->with(['entity_id' => 7, 'refunded_amount' => 10.0]);

        $orderManagement = $this->getFakeMock('Magento\Sales\Api\OrderManagementInterface')->getMock();
        $orderManagement->expects($this->once())->method('cancel')->with(376)
            ->willReturnCallback(function () {
                $this->assertFalse(BuckarooAdapter::$requestOnVoid, 'Nothing to void at Buckaroo');
                return true;
            });

        $refundPush = $this->getFakeMock('Buckaroo\Magento2\Model\Refund\Push')->getMock();
        $refundPush->expects($this->never())->method('receiveRefundPush');

        $instance = $this->makeProcessor($order, null, $groupTransaction, [
            'orderManagement' => $orderManagement,
            'orderRepository' => $this->makeOrderRepository($reloaded),
            'refundPush' => $refundPush,
        ]);

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
        $this->assertTrue(BuckarooAdapter::$requestOnVoid, 'Restored afterwards');
    }

    public function testTheRefundAlreadyCountedByTheRefundResponseIsNotCountedTwice(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);

        $groupTransaction = $this->makeGroupTransaction($this->makePart('300000376', 10.0), 0.0);
        $groupTransaction->expects($this->once())->method('updateGroupTransaction')
            ->with(['entity_id' => 7, 'refunded_amount' => 10.0]);

        $orderManagement = $this->getFakeMock('Magento\Sales\Api\OrderManagementInterface')->getMock();
        $orderManagement->expects($this->once())->method('cancel');

        $instance = $this->makeProcessor($order, null, $groupTransaction, [
            'orderManagement' => $orderManagement,
            'orderRepository' => $this->makeOrderRepository($this->getFakeMock(Order::class)->getMock()),
        ]);

        $this->invoke('handleRefundOfUnpaidPartialPayment', $instance);
    }

    public function testAnotherPartStillPaidKeepsTheOrderOpen(): void
    {
        $order = $this->makeOrder(false, 0.0);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('addCommentToStatusHistory')
            ->with($this->stringContains('was refunded'));

        $orderManagement = $this->getFakeMock('Magento\Sales\Api\OrderManagementInterface')->getMock();
        $orderManagement->expects($this->never())->method('cancel');

        $instance = $this->makeProcessor(
            $order,
            null,
            $this->makeGroupTransaction($this->makePart('300000376'), 5.0),
            [
                'orderManagement' => $orderManagement,
                'orderRepository' => $this->makeOrderRepository($order),
            ]
        );

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
    }

    public function testARepeatedPushChangesNothing(): void
    {
        $order = $this->makeOrder(false, 0.0, Order::STATE_CANCELED);
        $order->expects($this->never())->method('addCommentToStatusHistory');

        $groupTransaction = $this->makeGroupTransaction($this->makePart('300000376', 10.0), 0.0);
        $groupTransaction->expects($this->never())->method('updateGroupTransaction');

        $orderManagement = $this->getFakeMock('Magento\Sales\Api\OrderManagementInterface')->getMock();
        $orderManagement->expects($this->never())->method('cancel');

        $instance = $this->makeProcessor($order, null, $groupTransaction, ['orderManagement' => $orderManagement]);

        $this->assertTrue($this->invoke('handleRefundOfUnpaidPartialPayment', $instance));
    }

    /**
     * @param bool   $hasInvoices
     * @param float  $totalPaid
     * @param string $state
     *
     * @return object
     */
    private function makeOrder(bool $hasInvoices, float $totalPaid, string $state = Order::STATE_NEW)
    {
        $currency = $this->getFakeMock('Magento\Directory\Model\Currency')->getMock();
        $currency->method('formatTxt')->willReturnCallback(static fn($amount) => '€' . number_format($amount, 2));

        $order = $this->getFakeMock(Order::class)->getMock();
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getTotalPaid')->willReturn($totalPaid);
        $order->method('getIncrementId')->willReturn('300000376');
        $order->method('getId')->willReturn(376);
        $order->method('getState')->willReturn($state);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getOrderCurrency')->willReturn($currency);

        return $order;
    }

    /**
     * @param string $orderId
     * @param float  $refunded
     *
     * @return DataObject
     */
    private function makePart(string $orderId, float $refunded = 0.0): DataObject
    {
        return new DataObject([
            'id' => 7,
            'entity_id' => 7,
            'order_id' => $orderId,
            'transaction_id' => self::PART,
            'amount' => '10.00',
            'refunded_amount' => $refunded ?: null,
        ]);
    }

    /**
     * @param DataObject $part
     * @param float      $stillPaid
     *
     * @return object
     */
    private function makeGroupTransaction(DataObject $part, float $stillPaid)
    {
        $groupTransaction = $this->getFakeMock('Buckaroo\Magento2\Helper\PaymentGroupTransaction')->getMock();
        $groupTransaction->method('getGroupTransactionByTrxId')->with(self::PART)->willReturn($part);
        $groupTransaction->method('getGroupTransactionAmount')->willReturn($stillPaid);

        return $groupTransaction;
    }

    /**
     * @param object $order
     *
     * @return object
     */
    private function makeOrderRepository($order)
    {
        $repository = $this->getFakeMock('Magento\Sales\Api\OrderRepositoryInterface')->getMock();
        $repository->method('get')->willReturn($order);

        return $repository;
    }

    /**
     * @param object          $order
     * @param DataObject|null $part
     * @param object|null     $groupTransaction
     * @param array           $args
     *
     * @return object
     */
    private function makeProcessor($order, ?DataObject $part, $groupTransaction = null, array $args = [])
    {
        $pushRequest = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $pushRequest->method('getRelatedtransactionRefund')->willReturn(self::PART);
        $pushRequest->method('getAmountCredit')->willReturn('10.00');
        $pushRequest->method('getCurrency')->willReturn('EUR');

        $instance = $this->getInstance(array_merge([
            'groupTransaction' => $groupTransaction ?? $this->makeGroupTransaction($part, 0.0),
        ], $args));
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        return $instance;
    }
}
