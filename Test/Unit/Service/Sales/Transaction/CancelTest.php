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

namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Transaction;


use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\Order\Payment\Transaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Sales\Transaction\Cancel;
use Buckaroo\Magento2\Test\BaseTest;

class CancelTest extends BaseTest
{
    protected $instanceClass = Cancel::class;

    /**
     * @return array
     */
    public static function cancelProvider()
    {
        return [
            'neither allows cancel' => [
                false,
                false,
                0
            ],
            'account allows cancel' => [
                true,
                false,
                0
            ],
            'order allows cancel' => [
                false,
                true,
                0
            ],
            'both allows cancel' => [
                true,
                true,
                1
            ],
        ];
    }

    /**
     * @param $accountCancel
     * @param $orderCancel
     * @param $expectedCallCount
     *
     */
    #[DataProvider('cancelProvider')]
    public function testCancel($accountCancel, $orderCancel, $expectedCallCount)
    {
        $transactionMock = $this->getFakeMock(Transaction::class)->onlyMethods(['getOrder', 'getPaymentId'])->getMock();

        $paymentMock = $this->getFakeMock(Payment::class)->onlyMethods(['getMethodInstance', 'cancel'])->getMock();
        $paymentMock->method('getMethodInstance')->willReturnSelf();
        $paymentMock->expects($this->once())->method('cancel')->with($this->identicalTo($paymentMock));

        $paymentRepositoryMock = $this->getFakeMock(OrderPaymentRepositoryInterface::class)
            ->getMock();
        $paymentRepositoryMock->expects($this->once())->method('get')->with(77)->willReturn($paymentMock);
        $transactionMock->method('getPaymentId')->willReturn(77);

        $accountMock = $this->getFakeMock(Account::class)->onlyMethods(['getCancelOnFailed'])->getMock();
        $accountMock->expects($this->once())->method('getCancelOnFailed')->with(1)->willReturn($accountCancel);

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getStore', 'canCancel', 'getPayment', 'cancel', 'save', 'addCommentToStatusHistory'])
            ->getMock();
        $orderMock->method('getStore')->willReturn(1);
        $orderMock->expects($this->exactly((int)$accountCancel))->method('canCancel')->willReturn($orderCancel);
        $orderMock->expects($this->exactly($expectedCallCount))->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->exactly($expectedCallCount))->method('cancel')->willReturnSelf();
        $orderMock->expects($this->never())->method('save');
        $orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $orderRepositoryMock->expects($this->exactly(1 + $expectedCallCount))->method('save')->with($orderMock);

        $transactionMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance([
            'orderPaymentRepository' => $paymentRepositoryMock,
            'account' => $accountMock,
            'orderRepository' => $orderRepositoryMock
        ]);
        $instance->cancel($transactionMock);
    }

    /**
     * @return array
     */
    public static function cancelOrderProvider()
    {
        return [
            'method is not afterpay' => [
                'buckaroo_magento2_method',
                0
            ],
            'method is afterpay' => [
                'buckaroo_magento2_afterpay',
                1
            ],
            'method is afterpay2' => [
                'buckaroo_magento2_afterpay2',
                1
            ]
        ];
    }

    /**
     * @param $method
     * @param $expectedCallCount
     *
     */
    #[DataProvider('cancelOrderProvider')]
    public function testCancelOrder($method, $expectedCallCount)
    {
        $methodInstanceMock = $this->getMockBuilder(\Magento\Payment\Model\Method\AbstractMethod::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $methodInstanceMock->method('getCode')->willReturn($method);
        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethodInstance', 'setAdditionalInformation', 'save'])
            ->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);
        $paymentMock->expects($this->exactly($expectedCallCount))
            ->method('setAdditionalInformation')
            ->with('buckaroo_failed_authorize', 1);
        $paymentMock->expects($this->never())->method('save');

        $paymentRepositoryMock = $this->createMock(OrderPaymentRepositoryInterface::class);
        $paymentRepositoryMock->expects($this->exactly($expectedCallCount))->method('save')->with($paymentMock);

        $orderMock = $this->getFakeMock(Order::class)->onlyMethods(['getPayment', 'cancel', 'save'])->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->once())->method('cancel')->willReturnSelf();
        $orderMock->expects($this->never())->method('save');

        $orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);

        $instance = $this->getInstance([
            'orderPaymentRepository' => $paymentRepositoryMock,
            'orderRepository' => $orderRepositoryMock
        ]);
        $this->invokeArgs('cancelOrder', [$orderMock], $instance);
    }

    /**
     * @return array
     */
    public static function updateStatusProvider()
    {
        return [
            'order has canceled state' => [
                Order::STATE_CANCELED,
                'cancel'
            ],
            'order has different state' => [
                Order::STATE_PROCESSING,
                false
            ]
        ];
    }

    /**
     * @param $state
     * @param $expectedParameter
     *
     */
    #[DataProvider('updateStatusProvider')]
    public function testUpdateStatus($state, $expectedParameter)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getState', 'addCommentToStatusHistory', 'save'])
            ->getMock();
        $orderMock->method('getState')->willReturn($state);
        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('Payment status : Cancelled by consumer', $this->identicalTo($expectedParameter));
        $orderMock->expects($this->never())->method('save');

        $orderRepositoryMock = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $orderRepositoryMock->expects($this->once())->method('save')->with($orderMock);

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->onlyMethods(['get'])->getMock();
        $orderStatusFactoryMock->expects($this->once())->method('get')->with(890, $orderMock)->willReturn('cancel');

        $instance = $this->getInstance([
            'orderStatusFactory' => $orderStatusFactoryMock,
            'orderRepository' => $orderRepositoryMock
        ]);
        $this->invokeArgs('updateStatus', [$orderMock], $instance);
    }

    public function testCancelPayment()
    {
        $transactionMock = $this->getFakeMock(Transaction::class)->onlyMethods(['getPaymentId'])->getMock();
        $transactionMock->method('getPaymentId')->willReturn(123);

        $paymentMock = $this->getFakeMock(Payment::class)->onlyMethods(['getMethodInstance', 'cancel'])->getMock();
        $paymentMock->method('getMethodInstance')->willReturnSelf();
        $paymentMock->expects($this->once())->method('cancel')->with($this->identicalTo($paymentMock));

        $paymentRepositoryMock = $this->getFakeMock(OrderPaymentRepositoryInterface::class)
            ->getMock();
        $paymentRepositoryMock->expects($this->once())->method('get')->with(123)->willReturn($paymentMock);

        $instance = $this->getInstance(['orderPaymentRepository' => $paymentRepositoryMock]);
        $this->invokeArgs('cancelPayment', [$transactionMock], $instance);
    }
}
