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

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Observer\UpdateOrderStatus;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class UpdateOrderStatusTest extends BaseTest
{
    protected $instanceClass = UpdateOrderStatus::class;

    /**
     * A non-Buckaroo payment method must cause an early return: the order is
     * never loaded and the account configuration is never consulted.
     */
    public function testExecuteNotBuckarooDoesNothing()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('other_payment_method');
        $paymentMock->expects($this->never())->method('getOrder');

        $observerMock = $this->getFakeMock(ObserverStub::class)
            ->onlyMethods(['getPayment'])
            ->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $accountMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getOrderStatusNew', 'getCreateOrderBeforeTransaction'])
            ->getMock();
        $accountMock->expects($this->never())->method('getOrderStatusNew');
        $accountMock->expects($this->never())->method('getCreateOrderBeforeTransaction');

        $instance = $this->getInstance(['account' => $accountMock]);
        $instance->execute($observerMock);
    }

    /**
     * When the configured new status is one of the allowed statuses and the
     * order is still 'pending', the observer must set that status on the
     * order and add the matching status history comment.
     */
    public function testExecuteSetsAllowedConfiguredStatusOnPendingOrder()
    {
        $newStatus = 'pending_payment';

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getStore', 'getStatus', 'setStatus', 'addCommentToStatusHistory'])
            ->getMock();
        $orderMock->method('getStatus')->willReturn('pending');
        $orderMock->expects($this->once())
            ->method('setStatus')
            ->with($newStatus)
            ->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with(
                'Order status updated by Buckaroo payment placement to: ' . $newStatus,
                $newStatus
            );

        $observerMock = $this->createObserverForBuckarooPayment($orderMock);
        $accountMock = $this->createAccountMock($newStatus, false);

        $instance = $this->getInstance(['account' => $accountMock]);
        $instance->execute($observerMock);
    }

    /**
     * When the configured new status is processor-managed (e.g. 'processing'),
     * the observer must NOT change the order status and only add an
     * informational history comment.
     */
    public function testExecuteProcessorManagedStatusOnlyAddsComment()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getStore', 'getStatus', 'setStatus', 'addCommentToStatusHistory'])
            ->getMock();
        $orderMock->method('getStatus')->willReturn('pending');
        $orderMock->expects($this->never())->method('setStatus');
        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('Buckaroo payment placed. Status will be updated by payment processor response.');

        $observerMock = $this->createObserverForBuckarooPayment($orderMock);
        $accountMock = $this->createAccountMock('processing', false);

        $instance = $this->getInstance(['account' => $accountMock]);
        $instance->execute($observerMock);
    }

    /**
     * When "create order before transaction" is enabled, the observer must
     * leave the order untouched even for a Buckaroo payment.
     */
    public function testExecuteCreateOrderBeforeTransactionLeavesOrderUntouched()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getStore', 'getStatus', 'setStatus', 'addCommentToStatusHistory'])
            ->getMock();
        $orderMock->method('getStatus')->willReturn('pending');
        $orderMock->expects($this->never())->method('setStatus');
        $orderMock->expects($this->never())->method('addCommentToStatusHistory');

        $observerMock = $this->createObserverForBuckarooPayment($orderMock);
        $accountMock = $this->createAccountMock('pending_payment', true);

        $instance = $this->getInstance(['account' => $accountMock]);
        $instance->execute($observerMock);
    }

    /**
     * Build an observer mock exposing a Buckaroo payment for the given order.
     *
     * @param \PHPUnit\Framework\MockObject\MockObject $orderMock
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createObserverForBuckarooPayment($orderMock)
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $observerMock = $this->getFakeMock(ObserverStub::class)
            ->onlyMethods(['getPayment'])
            ->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        return $observerMock;
    }

    /**
     * Build an Account config mock with the given new-status configuration.
     *
     * @param string $newStatus
     * @param bool   $createOrderBeforeTransaction
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createAccountMock(string $newStatus, bool $createOrderBeforeTransaction)
    {
        $accountMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getOrderStatusNew', 'getCreateOrderBeforeTransaction'])
            ->getMock();
        $accountMock->method('getOrderStatusNew')->willReturn($newStatus);
        $accountMock->method('getCreateOrderBeforeTransaction')->willReturn($createOrderBeforeTransaction);

        return $accountMock;
    }
}
