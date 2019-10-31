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
namespace TIG\Buckaroo\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Observer\SendOrderConfirmation;
use TIG\Buckaroo\Test\BaseTest;

class SendOrderConfirmationTest extends BaseTest
{
    protected $instanceClass = SendOrderConfirmation::class;

    public function testExecuteNotBuckaroo()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getMethod'])->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('fake_method');

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteIsBuckarooNoOrderSend()
    {
        $orderMock = $this->getFakeMock(Order::class)->setMethods(['save', 'getStore'])->getMock();
        $orderMock->expects($this->once())->method('save')->willReturnSelf();

        $methodInstanceMock = $this->getFakeMock(MethodInterface::class)->getMock();
        $methodInstanceMock->usesRedirect = true;

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethod', 'getOrder', 'getMethodInstance'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('tig_buckaroo');
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturn($methodInstanceMock);

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteIsBuckarooOrderSend()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['save', 'getEmailSent', 'getStore', 'getIncrementId'])
            ->getMock();
        $orderMock->expects($this->once())->method('save')->willReturnSelf();
        $orderMock->expects($this->once())->method('getEmailSent')->willReturn(false);
        $orderMock->expects($this->exactly(2))->method('getStore');
        $orderMock->expects($this->once())->method('getIncrementId')->willReturn(rand(1, 100));

        $methodInstanceMock = $this->getFakeMock(MethodInterface::class)->getMock();
        $methodInstanceMock->usesRedirect = false;

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getMethod', 'getOrder', 'getMethodInstance'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('tig_buckaroo');
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturn($methodInstanceMock);

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $accountConfigMock = $this->getFakeMock(Account::class)->setMethods(['getOrderConfirmationEmail'])->getMock();
        $accountConfigMock->expects($this->once())->method('getOrderConfirmationEmail')->willReturn(true);

        $instance = $this->getInstance(['accountConfig' => $accountConfigMock]);
        $instance->execute($observerMock);
    }
}
