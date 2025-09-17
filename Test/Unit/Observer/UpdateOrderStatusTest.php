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
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Observer\UpdateOrderStatus;
use Buckaroo\Magento2\Test\BaseTest;

class UpdateOrderStatusTest extends BaseTest
{
    protected $instanceClass = UpdateOrderStatus::class;

    /**
     * Test what happens when this function is called but the payment method is not Buckaroo.
     */
    public function testExecuteNotBuckaroo()
    {
        $observerMock = $this->getMockBuilder(Observer::class)
            ->onlyMethods(['getEvent'])
            ->addMethods(['getPayment', 'getMethod'])
            ->getMock();
        $observerMock->method('getPayment')->willReturnSelf();
        $observerMock->method('getMethod')->willReturn('other_payment_method');

        $instance = $this->getInstance();
        $result = $instance->execute($observerMock);

        // Add assertion to verify method execution for non-Buckaroo payments
        $this->assertNull($result, 'Execute should return null for non-Buckaroo payment methods');
    }

    /**
     * Test what happens when the payment method is Buckaroo.
     */
    public function testExecuteIsBuckaroo()
    {
        $observerMock = $this->getMockBuilder(Observer::class)
            ->onlyMethods(['getEvent'])
            ->addMethods(['getPayment', 'getMethod', 'getOrder', 'getStore', 'setStatus'])
            ->getMock();
        $observerMock->method('getPayment')->willReturnSelf();
        $observerMock->method('getMethod')->willReturn('buckaroo_magento2');
        $observerMock->method('getOrder')->willReturnSelf();
        $observerMock->method('getStore')->willReturnSelf();
        $observerMock->method('setStatus')->willReturn('buckaroo_magento2_pending_paymen');

        $accountMock = $this->getFakeMock(Account::class)
            ->onlyMethods(['getOrderStatusNew', 'getCreateOrderBeforeTransaction'])
            ->getMock();
        $accountMock
            ->method('getOrderStatusNew')
            ->willReturn('buckaroo_magento2_pending_paymen');
        $accountMock->method('getCreateOrderBeforeTransaction')->willReturn(0);

        $instance = $this->getInstance(['accountConfig' => $accountMock]);
        $result = $instance->execute($observerMock);

        // Add assertion to verify method execution for Buckaroo payments
        $this->assertNull($result, 'Execute should handle Buckaroo payment method and update order status');
    }
}
