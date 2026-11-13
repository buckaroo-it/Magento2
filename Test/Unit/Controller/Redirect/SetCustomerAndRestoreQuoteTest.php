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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2;
use Buckaroo\Magento2\Controller\Redirect\Process;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Order;

/**
 * Covers Process::setCustomerAndRestoreQuote(): the quote is restored against the checkout
 * session and the customer session is left untouched.
 */
class SetCustomerAndRestoreQuoteTest extends BaseTest
{
    protected $instanceClass = Process::class;

    /**
     * The quote is restored while the customer session is left as-is.
     */
    public function testRestoresQuoteWithoutModifyingCustomerSession(): void
    {
        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['getIncrementId', 'getCustomerId'])
            ->getMock();
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getCustomerId')->willReturn(1);

        $customerSession = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn', 'setCustomerDataAsLoggedIn'])
            ->getMock();
        $customerSession->method('isLoggedIn')->willReturn(false);
        $customerSession->expects($this->never())->method('setCustomerDataAsLoggedIn');

        $checkoutSession = $this->getFakeMock(SessionStub2::class)
            ->onlyMethods(['getLastRealOrderId', 'setLastRealOrderId', 'restoreQuote'])
            ->getMock();
        $checkoutSession->method('getLastRealOrderId')->willReturn(null);
        $checkoutSession->expects($this->once())->method('setLastRealOrderId')->with('000000001');

        $instance = $this->getInstance([
            'customerSession' => $customerSession,
            'checkoutSession' => $checkoutSession,
        ]);
        $this->setProperty('order', $order, $instance);

        $this->invokeArgs('setCustomerAndRestoreQuote', ['failed'], $instance);
    }

    /**
     * Subclasses (e.g. IdinProcess) can reach this without an order set; it is a no-op then.
     */
    public function testIsANoopWhenNoOrderIsSet(): void
    {
        $customerSession = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['setCustomerDataAsLoggedIn'])
            ->getMock();
        $customerSession->expects($this->never())->method('setCustomerDataAsLoggedIn');

        $checkoutSession = $this->getFakeMock(SessionStub2::class)
            ->onlyMethods(['setLastRealOrderId'])
            ->getMock();
        $checkoutSession->expects($this->never())->method('setLastRealOrderId');

        $instance = $this->getInstance([
            'customerSession' => $customerSession,
            'checkoutSession' => $checkoutSession,
        ]);
        $this->setProperty('order', null, $instance);

        $this->invokeArgs('setCustomerAndRestoreQuote', ['success'], $instance);
        $this->addToAssertionCount(1);
    }
}
