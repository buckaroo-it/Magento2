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
namespace TIG\Buckaroo\Test\Unit\Service\Sales\Transaction;

use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\Order\Payment\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\OrderStatusFactory;
use TIG\Buckaroo\Service\Sales\Transaction\Cancel;
use TIG\Buckaroo\Test\BaseTest;

class CancelTest extends BaseTest
{
    protected $instanceClass = Cancel::class;

    /**
     * @return array
     */
    public function cancelProvider()
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
     * @dataProvider cancelProvider
     */
    public function testCancel($accountCancel, $orderCancel, $expectedCallCount)
    {
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethodInstance', 'cancel'])->getMock();
        $paymentMock->method('getMethodInstance')->willReturnSelf();

        $paymentRepositoryMock = $this->getFakeMock(OrderPaymentRepositoryInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $paymentRepositoryMock->expects($this->once())->method('get')->willReturn($paymentMock);

        $accountMock = $this->getFakeMock(Account::class)->setMethods(['getCancelOnFailed'])->getMock();
        $accountMock->expects($this->once())->method('getCancelOnFailed')->with(1)->willReturn($accountCancel);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getStore', 'canCancel', 'getPayment', 'cancel', 'addStatusHistoryComment', 'save'])
            ->getMock();
        $orderMock->expects($this->once())->method('getStore')->willReturn(1);
        $orderMock->expects($this->exactly((int)$accountCancel))->method('canCancel')->willReturn($orderCancel);
        $orderMock->expects($this->exactly($expectedCallCount))->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->exactly($expectedCallCount))->method('cancel')->willReturnSelf();
        $orderMock->expects($this->once())->method('addStatusHistoryComment');
        $orderMock->expects($this->exactly($expectedCallCount + 1))->method('save');

        $transactionMock = $this->getFakeMock(Transaction::class)->setMethods(['getOrder'])->getMock();
        $transactionMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance(['orderPaymentRepository' => $paymentRepositoryMock, 'account' => $accountMock]);
        $instance->cancel($transactionMock);
    }

    /**
     * @return array
     */
    public function cancelOrderProvider()
    {
        return [
            'method is afterpay' => [
                'tig_buckaroo_method',
                0
            ],
            'method is not afterpay' => [
                'tig_buckaroo_afterpay',
                1
            ]
        ];
    }

    /**
     * @param $method
     * @param $expectedCallCount
     *
     * @dataProvider cancelOrderProvider
     */
    public function testCancelOrder($method, $expectedCallCount)
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethodInstance', 'getCode', 'setAdditionalInformation', 'save'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->expects($this->once())->method('getCode')->willReturn($method);
        $paymentMock->expects($this->exactly($expectedCallCount))->method('setAdditionalInformation');
        $paymentMock->expects($this->exactly($expectedCallCount))->method('save');

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getPayment', 'cancel', 'save'])->getMock();
        $orderMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->once())->method('cancel')->willReturnSelf();
        $orderMock->expects($this->once())->method('save');

        $instance = $this->getInstance();
        $this->invokeArgs('cancelOrder', [$orderMock], $instance);
    }

    /**
     * @return array
     */
    public function updateStatusProvider()
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
     * @dataProvider updateStatusProvider
     */
    public function testUpdateStatus($state, $expectedParameter)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getState', 'addStatusHistoryComment', 'save'])
            ->getMock();
        $orderMock->expects($this->once())->method('getState')->willReturn($state);
        $orderMock->expects($this->once())->method('addStatusHistoryComment')
            ->with('Payment status : Cancelled by consumer', $expectedParameter);
        $orderMock->expects($this->once())->method('save');

        $orderStatusFactoryMock = $this->getFakeMock(OrderStatusFactory::class)->setMethods(['get'])->getMock();
        $orderStatusFactoryMock->expects($this->once())->method('get')->with(890, $orderMock)->willReturn('cancel');

        $instance = $this->getInstance(['orderStatusFactory' => $orderStatusFactoryMock]);
        $this->invokeArgs('updateStatus', [$orderMock], $instance);
    }

    public function testCancelPayment()
    {
        $transactionMock = $this->getFakeMock(Transaction::class)->setMethods(['getPaymentId'])->getMock();
        $transactionMock->expects($this->once())->method('getPaymentId')->willReturn(123);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethodInstance', 'cancel'])->getMock();
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->expects($this->once())->method('cancel')->with($paymentMock);

        $paymentRepositoryMock = $this->getFakeMock(OrderPaymentRepositoryInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $paymentRepositoryMock->expects($this->once())->method('get')->with(123)->willReturn($paymentMock);

        $instance = $this->getInstance(['orderPaymentRepository' => $paymentRepositoryMock]);
        $this->invokeArgs('cancelPayment', [$transactionMock], $instance);
    }
}
