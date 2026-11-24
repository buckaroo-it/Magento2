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

    public static function orderStateProvider(): array
    {
        return [
            'paid on the terminal'   => ['processing', 'https://shop.test/nl/checkout/onepage/success/', false],
            'failed on the terminal' => ['canceled', 'https://shop.test/nl/checkout/cart/', true],
            'still waiting'          => ['new', '', false],
            'paid, success redirect setting empty' => ['processing', 'https://shop.test/nl/checkout/onepage/success/', false, null],
            'failed, redirect to checkout setting on' => [
                'canceled', 'https://shop.test/nl/checkout/?bk_e=1#payment', true, 'checkout/onepage/success', true
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('orderStateProvider')]
    public function testRedirectFollowsTheOrderState(
        string $state,
        string $expectedRedirect,
        bool $restoresCart,
        ?string $successRedirect = 'checkout/onepage/success',
        bool $failureToCheckout = false
    ): void
    {
        $store = $this->getFakeMock(\Magento\Store\Model\Store::class)->onlyMethods(['getId'])->getMock();
        $store->method('getId')->willReturn(2);

        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['loadByIncrementId', 'getStore'])
            ->getMock();
        $order->method('getStore')->willReturn($store);
        $order->setData(['id' => 5, 'entity_id' => 5, 'increment_id' => '100000123', 'state' => $state]);

        $accountConfig = $this->getFakeMock(\Buckaroo\Magento2\Model\ConfigProvider\Account::class)
            ->onlyMethods(['getSuccessRedirect', 'getFailureRedirect', 'getFailureRedirectToCheckout'])
            ->getMock();
        $accountConfig->method('getFailureRedirectToCheckout')->willReturn($failureToCheckout ? '1' : '0');
        $accountConfig->method('getSuccessRedirect')->willReturn($successRedirect);
        $accountConfig->method('getFailureRedirect')->willReturn('checkout/cart');

        $urlBuilder = $this->getMockForAbstractClass(\Magento\Framework\UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            function (string $route, array $params) {
                $this->assertSame(2, $params['_scope']);
                $query = isset($params['_query']) ? '?' . http_build_query($params['_query']) : '';
                return 'https://shop.test/nl/' . $route . '/' . $query;
            }
        );

        $checkoutSession = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2::class)
            ->onlyMethods(['getLastRealOrderId', 'restoreQuote'])->getMock();
        $checkoutSession->method('getLastRealOrderId')->willReturn('100000123');
        $checkoutSession->expects($restoresCart ? $this->once() : $this->never())->method('restoreQuote');

        $customerSession = $this->getFakeMock(CustomerSession::class)->onlyMethods(['getCustomerId'])->getMock();
        $customerSession->method('getCustomerId')->willReturn(null);

        $request = $this->getMockForAbstractClass(\Magento\Framework\App\RequestInterface::class);
        $request->method('getParams')->willReturn(['orderId' => '100000123']);

        $messageManager = $this->getMockForAbstractClass(\Magento\Framework\Message\ManagerInterface::class);
        $messageManager->expects($restoresCart ? $this->once() : $this->never())->method('addErrorMessage');

        $json = $this->getFakeMock(\Magento\Framework\Controller\Result\Json::class)
            ->onlyMethods(['setData'])->getMock();
        $json->expects($this->once())->method('setData')
            ->with(['success' => 'true', 'redirect' => $expectedRedirect])
            ->willReturnSelf();
        $jsonFactory = $this->getFakeMock(\Magento\Framework\Controller\Result\JsonFactory::class)
            ->onlyMethods(['create'])->getMock();
        $jsonFactory->method('create')->willReturn($json);

        $instance = $this->objectManagerHelper->getObject(CheckOrderStatus::class, []);
        foreach ([
            '_request'          => $request,
            'messageManager'    => $messageManager,
            '_actionFlag'       => $this->getFakeMock(\Magento\Framework\App\ActionFlag::class, true),
            'order'             => $order,
            'accountConfig'     => $accountConfig,
            'urlBuilder'        => $urlBuilder,
            'checkoutSession'   => $checkoutSession,
            'customerSession'   => $customerSession,
            'resultJsonFactory' => $jsonFactory,
        ] as $property => $value) {
            $this->setProperty($property, $value, $instance);
        }

        $instance->execute();
    }

    public function testStoreUrlKeepsAFragment(): void
    {
        $urlBuilder = $this->getMockForAbstractClass(\Magento\Framework\UrlInterface::class);
        $urlBuilder->method('getUrl')->with('checkout', ['_scope' => 2])->willReturn('https://shop.test/checkout/');

        $instance = $this->objectManagerHelper->getObject(CheckOrderStatus::class, []);
        $this->setProperty('urlBuilder', $urlBuilder, $instance);

        $this->assertSame(
            'https://shop.test/checkout#payment',
            $this->invokeArgs('getStoreUrl', ['checkout#payment', 2], $instance)
        );
    }
}
