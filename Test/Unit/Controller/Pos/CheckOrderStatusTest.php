<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Controller\Pos;

use Buckaroo\Magento2\Controller\Pos\CheckOrderStatus;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Order;

/**
 * Covers the order-ownership guard: comparing customer ids alone lets a guest
 * order in an anonymous session pass (null === null), which would expose the
 * state of any order whose increment id is guessed.
 */
class CheckOrderStatusTest extends BaseTest
{
    protected $instanceClass = CheckOrderStatus::class;

    /**
     * @param int|null $sessionCustomerId
     * @param int|null $orderCustomerId
     * @param string $orderIncrementId
     * @param string|null $sessionLastRealOrderId
     * @return CheckOrderStatus
     */
    private function prepareInstance(
        $sessionCustomerId,
        $orderCustomerId,
        string $orderIncrementId,
        $sessionLastRealOrderId
    ) {
        $customerSession = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['getCustomerId'])->disableOriginalConstructor()->getMock();
        $customerSession->method('getCustomerId')->willReturn($sessionCustomerId);

        $checkoutSession = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2::class)
            ->onlyMethods(['getLastRealOrderId'])->disableOriginalConstructor()->getMock();
        $checkoutSession->method('getLastRealOrderId')->willReturn($sessionLastRealOrderId);

        $order = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $order->setData(['customer_id' => $orderCustomerId, 'increment_id' => $orderIncrementId]);

        $instance = $this->objectManagerHelper->getObject(CheckOrderStatus::class, []);
        $this->setProperty('customerSession', $customerSession, $instance);
        $this->setProperty('checkoutSession', $checkoutSession, $instance);
        $this->setProperty('order', $order, $instance);

        return $instance;
    }

    public function testGuestOrderIsRejectedForAnonymousSessionWithoutMatchingLastOrder(): void
    {
        // Anonymous session (customer id null) probing a guest order (customer id null)
        $instance = $this->prepareInstance(null, null, '100000123', null);

        $this->assertFalse($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }

    public function testGuestOrderIsRejectedWhenSessionPlacedADifferentOrder(): void
    {
        $instance = $this->prepareInstance(null, null, '100000123', '100000999');

        $this->assertFalse($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }

    public function testGuestOrderIsAcceptedWhenItIsTheSessionsOwnOrder(): void
    {
        $instance = $this->prepareInstance(null, null, '100000123', '100000123');

        $this->assertTrue($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }

    public function testCustomerOrderIsAcceptedForItsOwner(): void
    {
        $instance = $this->prepareInstance(42, 42, '100000123', null);

        $this->assertTrue($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }

    public function testCustomerOrderIsRejectedForAnotherCustomer(): void
    {
        $instance = $this->prepareInstance(42, 77, '100000123', null);

        $this->assertFalse($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }

    public function testCustomerSessionCannotClaimGuestOrderItDidNotPlace(): void
    {
        // Logged-in customer, guest order (no customer id): falls back to the session's
        // own last order, which is a different one
        $instance = $this->prepareInstance(42, null, '100000123', '100000999');

        $this->assertFalse($this->invoke('isOrderOwnedByCurrentVisitor', $instance));
    }
}
