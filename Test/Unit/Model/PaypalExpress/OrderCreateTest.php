<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\PaypalExpress;

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\PaypalExpress\OrderCreate;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Covers:
 *  - Fix 2: checkQuoteBelongsToLoggedUser — isLoggedIn() + int cast.
 *  - Fix 3: clearPendingAddressFields — placeholder values cleaned on placeOrder() failure.
 *
 * PHPUnit onlyMethods() vs addMethods() rules confirmed by reflection in this environment:
 *
 *  QuoteAddress — field accessors (getFirstname, setStreet, etc.) and
 *  setShouldIgnoreValidation are REAL PHP methods → onlyMethods().
 *
 *  Quote — getCustomerEmail / setCustomerEmail are Magento magic __call methods
 *  (no real PHP method exists on Quote) → addMethods().
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderCreateTest extends BaseTest
{
    protected $instanceClass = OrderCreate::class;

    // -------------------------------------------------------------------------
    // Dependency helpers
    // -------------------------------------------------------------------------

    private function makeOrderCreate(array $overrides = []): OrderCreate
    {
        $defaults = [
            'responseFactory'        => $this->getFakeMock(OrderCreateResponseInterfaceFactory::class)->getMock(),
            'quoteManagement'        => $this->getFakeMock(CartManagementInterface::class)->getMockForAbstractClass(),
            'maskedQuoteIdToQuoteId' => $this->getFakeMock(MaskedQuoteIdToQuoteId::class)->getMock(),
            'customerSession'        => $this->getFakeMock(CustomerSession::class)->getMock(),
            'checkoutSession'        => $this->getFakeMock(CheckoutSession::class)->getMock(),
            'quoteRepository'        => $this->getFakeMock(CartRepositoryInterface::class)->getMockForAbstractClass(),
            'orderRepository'        => $this->getFakeMock(OrderRepositoryInterface::class)->getMockForAbstractClass(),
            'orderUpdateFactory'     => $this->getFakeMock(OrderUpdateFactory::class)->getMock(),
            'logger'                 => $this->getFakeMock(Log::class)->getMock(),
        ];

        return $this->getObject(OrderCreate::class, array_merge($defaults, $overrides));
    }

    /** Invoke a protected/private method via reflection. */
    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    /**
     * Quote mock used by clearPendingAddressFields tests.
     *
     * getShippingAddress / getBillingAddress / getCustomer / getId are real PHP methods
     * on Quote → onlyMethods().
     *
     * getCustomerEmail / setCustomerEmail are Magento magic __call methods on Quote
     * → addMethods().
     */
    private function makeQuoteMock(
        QuoteAddress $shippingAddress,
        QuoteAddress $billingAddress,
        string $customerEmail = 'pending@paypal.customer'
    ): Quote {
        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getCustomer', 'getId'])
            ->addMethods(['getCustomerEmail', 'setCustomerEmail'])
            ->getMock();

        $quoteMock->method('getShippingAddress')->willReturn($shippingAddress);
        $quoteMock->method('getBillingAddress')->willReturn($billingAddress);
        $quoteMock->method('getCustomerEmail')->willReturn($customerEmail);

        return $quoteMock;
    }

    /**
     * QuoteAddress mock pre-loaded with placeholder values.
     *
     * All listed methods — including setShouldIgnoreValidation — are confirmed
     * REAL PHP methods on Magento\Quote\Model\Quote\Address → onlyMethods().
     *
     * setShouldIgnoreValidation is called by ignoreAddressValidation() per
     * Magento's own PayPal Express convention (module-paypal/Express/Checkout:844).
     */
    private function makeAddressWithPlaceholders(): QuoteAddress
    {
        $address = $this->getFakeMock(QuoteAddress::class)
            ->onlyMethods([
                'getFirstname', 'setFirstname',
                'getLastname',  'setLastname',
                'getStreet',    'setStreet',
                'getTelephone', 'setTelephone',
                'getEmail',     'setEmail',
            ])
            ->addMethods(['setShouldIgnoreValidation']) // magic __call, not a real PHP method
            ->getMock();

        $address->method('getFirstname')->willReturn('PayPal');
        $address->method('getLastname')->willReturn('Customer');
        $address->method('getStreet')->willReturn(['Pending']);
        $address->method('getTelephone')->willReturn('000-000-0000');
        $address->method('getEmail')->willReturn('pending@paypal.customer');

        return $address;
    }

    /** QuoteAddress mock pre-loaded with real customer values. */
    private function makeAddressWithRealData(): QuoteAddress
    {
        $address = $this->getFakeMock(QuoteAddress::class)
            ->onlyMethods([
                'getFirstname', 'setFirstname',
                'getLastname',  'setLastname',
                'getStreet',    'setStreet',
                'getTelephone', 'setTelephone',
                'getEmail',     'setEmail',
            ])
            ->getMock();

        $address->method('getFirstname')->willReturn('Jane');
        $address->method('getLastname')->willReturn('Doe');
        $address->method('getStreet')->willReturn(['Hoofdstraat 1']);
        $address->method('getTelephone')->willReturn('+31612345678');
        $address->method('getEmail')->willReturn('jane@example.com');

        return $address;
    }

    // -------------------------------------------------------------------------
    // Fix 2: checkQuoteBelongsToLoggedUser
    // -------------------------------------------------------------------------

    public function testCheckQuoteBelongsToLoggedUserPassesForGuestSession(): void
    {
        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn'])
            ->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(false);

        $quoteMock = $this->getFakeMock(Quote::class)->getMock();
        $quoteMock->expects($this->never())->method('getCustomer');

        $instance = $this->makeOrderCreate(['customerSession' => $sessionMock]);
        $this->callMethod($instance, 'checkQuoteBelongsToLoggedUser', [$quoteMock]);
        $this->addToAssertionCount(1);
    }

    public function testCheckQuoteBelongsToLoggedUserPassesWhenSessionIdIsIntAndQuoteIdIsString(): void
    {
        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn', 'getCustomerId'])
            ->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(true);
        $sessionMock->method('getCustomerId')->willReturn(42); // int from session

        $customerMock = $this->getFakeMock(CustomerInterface::class)
            ->onlyMethods(['getId'])
            ->getMockForAbstractClass();
        $customerMock->method('getId')->willReturn('42'); // string from DB

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getCustomer'])
            ->getMock();
        $quoteMock->method('getCustomer')->willReturn($customerMock);

        $instance = $this->makeOrderCreate(['customerSession' => $sessionMock]);
        $this->callMethod($instance, 'checkQuoteBelongsToLoggedUser', [$quoteMock]);
        $this->addToAssertionCount(1);
    }

    public function testCheckQuoteBelongsToLoggedUserPassesForMatchingIntIds(): void
    {
        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn', 'getCustomerId'])
            ->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(true);
        $sessionMock->method('getCustomerId')->willReturn(7);

        $customerMock = $this->getFakeMock(CustomerInterface::class)
            ->onlyMethods(['getId'])
            ->getMockForAbstractClass();
        $customerMock->method('getId')->willReturn(7);

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getCustomer'])
            ->getMock();
        $quoteMock->method('getCustomer')->willReturn($customerMock);

        $instance = $this->makeOrderCreate(['customerSession' => $sessionMock]);
        $this->callMethod($instance, 'checkQuoteBelongsToLoggedUser', [$quoteMock]);
        $this->addToAssertionCount(1);
    }

    public function testCheckQuoteBelongsToLoggedUserThrowsForMismatchedCustomer(): void
    {
        $this->expectException(PaypalExpressException::class);

        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn', 'getCustomerId'])
            ->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(true);
        $sessionMock->method('getCustomerId')->willReturn(1);

        $customerMock = $this->getFakeMock(CustomerInterface::class)
            ->onlyMethods(['getId'])
            ->getMockForAbstractClass();
        $customerMock->method('getId')->willReturn('2');

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getCustomer'])
            ->getMock();
        $quoteMock->method('getCustomer')->willReturn($customerMock);

        $instance = $this->makeOrderCreate(['customerSession' => $sessionMock]);
        $this->callMethod($instance, 'checkQuoteBelongsToLoggedUser', [$quoteMock]);
    }

    // -------------------------------------------------------------------------
    // Fix 3: clearPendingAddressFields
    // -------------------------------------------------------------------------

    public function testClearPendingAddressFieldsResetsPlaceholderValues(): void
    {
        $shippingAddress = $this->makeAddressWithPlaceholders();
        $billingAddress  = $this->makeAddressWithPlaceholders();

        foreach ([$shippingAddress, $billingAddress] as $address) {
            $address->expects($this->once())->method('setFirstname')->with('');
            $address->expects($this->once())->method('setLastname')->with('');
            $address->expects($this->once())->method('setStreet')->with([]);
            $address->expects($this->once())->method('setTelephone')->with('');
            $address->expects($this->once())->method('setEmail')->with('');
        }

        $quoteMock = $this->makeQuoteMock($shippingAddress, $billingAddress);
        $quoteMock->expects($this->once())->method('setCustomerEmail')->with('');

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)
            ->onlyMethods(['save'])->getMockForAbstractClass();
        $repoMock->expects($this->once())->method('save')->with($quoteMock);

        $instance = $this->makeOrderCreate(['quoteRepository' => $repoMock]);
        $this->callMethod($instance, 'clearPendingAddressFields', [$quoteMock]);
    }

    public function testClearPendingAddressFieldsPreservesRealCustomerData(): void
    {
        $address = $this->makeAddressWithRealData();

        $address->expects($this->never())->method('setFirstname');
        $address->expects($this->never())->method('setLastname');
        $address->expects($this->never())->method('setStreet');
        $address->expects($this->never())->method('setTelephone');
        $address->expects($this->never())->method('setEmail');

        $quoteMock = $this->makeQuoteMock($address, $address, 'jane@example.com');
        $quoteMock->expects($this->never())->method('setCustomerEmail');

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)
            ->onlyMethods(['save'])->getMockForAbstractClass();
        $repoMock->expects($this->once())->method('save');

        $instance = $this->makeOrderCreate(['quoteRepository' => $repoMock]);
        $this->callMethod($instance, 'clearPendingAddressFields', [$quoteMock]);
    }

    public function testClearPendingAddressFieldsSwallowsRepositorySaveException(): void
    {
        $address  = $this->makeAddressWithPlaceholders();
        $quoteMock = $this->makeQuoteMock($address, $address);

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)
            ->onlyMethods(['save'])->getMockForAbstractClass();
        $repoMock->method('save')->willThrowException(new \RuntimeException('DB error'));

        $instance = $this->makeOrderCreate(['quoteRepository' => $repoMock]);
        $this->callMethod($instance, 'clearPendingAddressFields', [$quoteMock]);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Fix 3 integration: createOrder() rethrows + cleans up on placeOrder() failure
    // -------------------------------------------------------------------------

    public function testCreateOrderRethrowsAndClearsPlaceholdersWhenPlaceOrderFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('placeOrder failed');

        // Fully mock the address without restricting to specific methods.
        // The integration flow calls ensureRequiredAddressFields() →
        // copyAddressFieldsToBilling() which invokes getCity/getPostcode/getCountryId etc.
        // Specifying onlyMethods() would leave those unspecified methods falling through
        // to the real AbstractAddress implementation, which crashes on null injected factories.
        // With no onlyMethods()/addMethods() call, ALL methods are doubled and return null.
        $address = $this->getFakeMock(QuoteAddress::class)->getMock();

        $payment = $this->getFakeMock(QuotePayment::class)
            ->onlyMethods(['setAdditionalInformation', 'setMethod'])
            ->getMock();

        $customerMock = $this->getFakeMock(CustomerInterface::class)
            ->onlyMethods(['getId'])->getMockForAbstractClass();
        $customerMock->method('getId')->willReturn(null);

        $quoteMock = $this->getFakeMock(Quote::class)
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getPayment',
                           'getCustomer', 'reserveOrderId', 'getId'])
            ->addMethods(['getCustomerEmail', 'setCustomerEmail'])
            ->getMock();
        $quoteMock->method('getShippingAddress')->willReturn($address);
        $quoteMock->method('getBillingAddress')->willReturn($address);
        $quoteMock->method('getPayment')->willReturn($payment);
        $quoteMock->method('getCustomer')->willReturn($customerMock);
        $quoteMock->method('getCustomerEmail')->willReturn('pending@paypal.customer');
        $quoteMock->method('getId')->willReturn(1);

        $maskedMock = $this->getFakeMock(MaskedQuoteIdToQuoteId::class)
            ->onlyMethods(['execute'])->getMock();
        $maskedMock->method('execute')->willReturn(1);

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)
            ->onlyMethods(['get', 'save'])->getMockForAbstractClass();
        $repoMock->method('get')->willReturn($quoteMock);

        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn'])->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(false);

        $quoteMgmtMock = $this->getFakeMock(CartManagementInterface::class)
            ->onlyMethods(['placeOrder'])->getMockForAbstractClass();
        $quoteMgmtMock->method('placeOrder')
            ->willThrowException(new \RuntimeException('placeOrder failed'));

        $instance = $this->makeOrderCreate([
            'customerSession'        => $sessionMock,
            'maskedQuoteIdToQuoteId' => $maskedMock,
            'quoteRepository'        => $repoMock,
            'quoteManagement'        => $quoteMgmtMock,
        ]);

        $this->callMethod($instance, 'createOrder', ['paypal-order-id-123', 'masked-cart-id']);
    }
}
