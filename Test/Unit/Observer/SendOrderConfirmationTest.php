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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Observer\SendOrderConfirmation;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;

class SendOrderConfirmationTest extends BaseTest
{
    protected $instanceClass = SendOrderConfirmation::class;

    /**
     * A non-Buckaroo payment must cause an early return: no order lookup and
     * no confirmation email.
     */
    public function testExecuteNotBuckarooNeverSendsEmail()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('fake_method');
        $paymentMock->expects($this->never())->method('getOrder');

        $orderSenderMock = $this->createOrderSenderMock();
        $orderSenderMock->expects($this->never())->method('send');

        $instance = $this->getInstance([
            'orderSender' => $orderSenderMock,
            'logger' => $this->createLoggerMock(),
        ]);
        $instance->execute($this->createObserverMock($paymentMock));
    }

    /**
     * A redirect payment method must skip sending the confirmation email.
     */
    public function testExecuteRedirectMethodNeverSendsEmail()
    {
        $orderMock = $this->getFakeMock(Order::class, true);
        $paymentMock = $this->createBuckarooPaymentMock($orderMock, true);

        $orderSenderMock = $this->createOrderSenderMock();
        $orderSenderMock->expects($this->never())->method('send');

        $accountConfigMock = $this->createAccountConfigMock();
        $accountConfigMock->expects($this->never())->method('getOrderConfirmationEmail');

        $instance = $this->getInstance([
            'accountConfig' => $accountConfigMock,
            'orderSender' => $orderSenderMock,
            'logger' => $this->createLoggerMock(),
        ]);
        $instance->execute($this->createObserverMock($paymentMock));
    }

    /**
     * A non-redirect Buckaroo payment with confirmation email enabled, no
     * email sent yet and a real increment id must send the confirmation
     * email exactly once, in forced sync mode.
     */
    public function testExecuteSendsConfirmationEmailOnce()
    {
        $orderMock = $this->createOrderMock(false);
        $paymentMock = $this->createBuckarooPaymentMock($orderMock, false);

        $orderSenderMock = $this->createOrderSenderMock();
        $orderSenderMock->expects($this->once())
            ->method('send')
            ->with($orderMock, true)
            ->willReturn(true);

        $accountConfigMock = $this->createAccountConfigMock();
        $accountConfigMock->method('getOrderConfirmationEmail')->willReturn(true);
        $accountConfigMock->method('getCreateOrderBeforeTransaction')->willReturn(false);

        $instance = $this->getInstance([
            'accountConfig' => $accountConfigMock,
            'orderSender' => $orderSenderMock,
            'logger' => $this->createLoggerMock(),
        ]);
        $instance->execute($this->createObserverMock($paymentMock));
    }

    /**
     * When the order email was already sent, the observer must not send it
     * again even though everything else allows sending.
     */
    public function testExecuteEmailAlreadySentNeverSendsEmail()
    {
        $orderMock = $this->createOrderMock(true);
        $paymentMock = $this->createBuckarooPaymentMock($orderMock, false);

        $orderSenderMock = $this->createOrderSenderMock();
        $orderSenderMock->expects($this->never())->method('send');

        $accountConfigMock = $this->createAccountConfigMock();
        $accountConfigMock->method('getOrderConfirmationEmail')->willReturn(true);
        $accountConfigMock->method('getCreateOrderBeforeTransaction')->willReturn(false);

        $instance = $this->getInstance([
            'accountConfig' => $accountConfigMock,
            'orderSender' => $orderSenderMock,
            'logger' => $this->createLoggerMock(),
        ]);
        $instance->execute($this->createObserverMock($paymentMock));
    }

    /**
     * @param bool $emailSent
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createOrderMock(bool $emailSent)
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getEmailSent', 'getIncrementId', 'getStore', 'getStoreId', 'getId'])
            ->getMock();
        $orderMock->method('getEmailSent')->willReturn($emailSent);
        $orderMock->method('getIncrementId')->willReturn('100000001');
        $orderMock->method('getId')->willReturn(1);

        return $orderMock;
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject $orderMock
     * @param bool                                     $usesRedirect
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createBuckarooPaymentMock($orderMock, bool $usesRedirect)
    {
        $methodInstance = new \stdClass();
        $methodInstance->usesRedirect = $usesRedirect;

        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder', 'getMethodInstance'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($methodInstance);

        return $paymentMock;
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject $paymentMock
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createObserverMock($paymentMock)
    {
        $observerMock = $this->getFakeMock(ObserverStub::class)
            ->onlyMethods(['getPayment'])
            ->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        return $observerMock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createOrderSenderMock()
    {
        return $this->getFakeMock(OrderSender::class)
            ->onlyMethods(['send'])
            ->getMock();
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createAccountConfigMock()
    {
        return $this->getFakeMock(Account::class)
            ->onlyMethods(['getOrderConfirmationEmail', 'getCreateOrderBeforeTransaction'])
            ->getMock();
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createLoggerMock()
    {
        return $this->getMockBuilder(BuckarooLoggerInterface::class)->getMock();
    }
}
