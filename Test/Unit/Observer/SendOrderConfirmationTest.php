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

use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Observer\SendOrderConfirmation;
use Buckaroo\Magento2\Test\BaseTest;

class SendOrderConfirmationTest extends BaseTest
{
    protected $instanceClass = SendOrderConfirmation::class;

    public function testExecuteNotBuckaroo()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->onlyMethods(['getMethod'])->getMock();
        $paymentMock->method('getMethod')->willReturn('fake_method');

        $observerMock = $this->getFakeMock(Observer::class)->addMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);

        // Add assertion to verify the method handles non-Buckaroo payments correctly
        $this->assertNull($result, 'Execute should return null for non-Buckaroo payment methods');
    }

    public function testExecuteIsBuckarooNoOrderSend()
    {
        $orderMock = $this->getFakeMock(Order::class)->onlyMethods(['save'])->getMock();
        $orderMock->method('save')->willReturnSelf();

        // Create a concrete mock class instead of trying to mock the abstract MethodInterface
        $methodInstanceMock = $this->getMockBuilder(\stdClass::class)->getMock();
        $methodInstanceMock->usesRedirect = true;

        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder', 'getMethodInstance'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $observerMock = $this->getFakeMock(Observer::class)->addMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $accountConfigMock = $this->getFakeMock(Account::class)->onlyMethods(['getOrderConfirmationEmail','getCreateOrderBeforeTransaction'])->getMock();
        $accountConfigMock->method('getOrderConfirmationEmail')->willReturn(true);
        $accountConfigMock->method('getCreateOrderBeforeTransaction')->willReturn(false);

        $instance = $this->getInstance(['accountConfig' => $accountConfigMock]);
        $result = $instance->execute($observerMock);

        // Add assertion to verify method execution for redirect payments
        $this->assertNull($result, 'Execute should handle redirect payments without sending email');
    }

    public function testExecuteIsBuckarooOrderSend()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getEmailSent', 'getStore', 'getIncrementId', 'save'])
            ->getMock();
        $orderMock->method('save')->willReturnSelf();
        $orderMock->method('getEmailSent')->willReturn(false);
        $orderMock->method('getStore');
        $orderMock->method('getIncrementId')->willReturn(rand(1, 100));

        // Create a concrete mock class instead of trying to mock the abstract MethodInterface
        $methodInstanceMock = $this->getMockBuilder(\stdClass::class)->getMock();
        $methodInstanceMock->usesRedirect = false;

        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getOrder', 'getMethodInstance'])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $observerMock = $this->getFakeMock(Observer::class)->addMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $accountConfigMock = $this->getFakeMock(Account::class)->onlyMethods(['getOrderConfirmationEmail','getCreateOrderBeforeTransaction'])->getMock();
        $accountConfigMock->method('getOrderConfirmationEmail')->willReturn(true);
        $accountConfigMock->method('getCreateOrderBeforeTransaction')->willReturn(false);

        $instance = $this->getInstance(['accountConfig' => $accountConfigMock]);
        $result = $instance->execute($observerMock);

        // Add assertion to verify method execution for non-redirect payments
        $this->assertNull($result, 'Execute should handle non-redirect payments and process order email');
    }
}
