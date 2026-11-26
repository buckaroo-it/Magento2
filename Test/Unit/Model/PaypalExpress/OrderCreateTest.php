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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\PaypalExpress;

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\PaypalExpress\OrderCreate;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ChangeQuoteControlInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdate;
use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterface;

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
            'quoteManagement'        => $this->getFakeMock(CartManagementInterface::class)->getMock(),
            'maskedQuoteIdToQuoteId' => $this->getFakeMock(MaskedQuoteIdToQuoteId::class)->getMock(),
            'customerSession'        => $this->getFakeMock(CustomerSession::class)->getMock(),
            'checkoutSession'        => $this->getFakeMock(CheckoutSession::class)->getMock(),
            'quoteRepository'        => $this->getFakeMock(CartRepositoryInterface::class)->getMock(),
            'orderRepository'        => $this->getFakeMock(OrderRepositoryInterface::class)->getMock(),
            'orderUpdateFactory'     => $this->getFakeMock(OrderUpdateFactory::class)->getMock(),
            'logger'                 => $this->getFakeMock(Log::class)->getMock(),
            'changeQuoteControl'     => $this->makeChangeQuoteControl(true),
        ];

        return $this->getObject(OrderCreate::class, array_merge($defaults, $overrides));
    }

    /** A ChangeQuoteControl that allows or denies every quote. */
    private function makeChangeQuoteControl(bool $allowed): ChangeQuoteControlInterface
    {
        $mock = $this->getFakeMock(ChangeQuoteControlInterface::class)->getMock();
        $mock->method('isAllowed')->willReturn($allowed);

        return $mock;
    }

    /** Invoke a protected/private method via reflection. */
    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
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
        $quoteMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getCustomer', 'getId', 'getCustomerEmail', 'setCustomerEmail'])->getMock();

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
        $address = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class) // magic __call, not a real PHP method
            ->onlyMethods(['getFirstname', 'setFirstname', 'getLastname', 'setLastname', 'getStreet', 'setStreet', 'getTelephone', 'setTelephone', 'getEmail', 'setEmail', 'setShouldIgnoreValidation'])->getMock();

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
            ->getMock();
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
            ->getMock();
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
            ->getMock();
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

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
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

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repoMock->expects($this->once())->method('save');

        $instance = $this->makeOrderCreate(['quoteRepository' => $repoMock]);
        $this->callMethod($instance, 'clearPendingAddressFields', [$quoteMock]);
    }

    public function testClearPendingAddressFieldsSwallowsRepositorySaveException(): void
    {
        $address  = $this->makeAddressWithPlaceholders();
        $quoteMock = $this->makeQuoteMock($address, $address);

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repoMock->method('save')->willThrowException(new \RuntimeException('DB error'));

        $instance = $this->makeOrderCreate(['quoteRepository' => $repoMock]);
        $this->callMethod($instance, 'clearPendingAddressFields', [$quoteMock]);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Fix 3 integration: createOrder() rethrows + cleans up on placeOrder() failure
    // -------------------------------------------------------------------------

    /**
     * Address double that keeps the values written by the code under test.
     *
     * All methods are doubled (copyAddressFieldsToBilling() calls getCity() etc., which would
     * otherwise reach the real AbstractAddress); the contact fields read back what was set.
     *
     * @param array $state
     *
     * @return QuoteAddress
     */
    private function makeStatefulAddress(array &$state): QuoteAddress
    {
        $address = $this->getFakeMock(QuoteAddress::class)->getMock();
        foreach (['Firstname', 'Lastname', 'Street', 'Telephone', 'Email'] as $field) {
            $address->method('get' . $field)->willReturnCallback(function () use (&$state, $field) {
                return $state[$field] ?? null;
            });
            $address->method('set' . $field)->willReturnCallback(
                function ($value) use (&$state, $field, $address) {
                    $state[$field] = $value;
                    return $address;
                }
            );
        }

        return $address;
    }

    public function testCreateOrderRethrowsAndClearsPlaceholdersWhenPlaceOrderFails(): void
    {
        $shippingState = [];
        $billingState  = ['Firstname' => 'Jane', 'Street' => ['Hoofdstraat 1']];
        $shippingAddress = $this->makeStatefulAddress($shippingState);
        $billingAddress  = $this->makeStatefulAddress($billingState);

        $payment = $this->getFakeMock(QuotePayment::class)
            ->onlyMethods(['setAdditionalInformation', 'setMethod'])
            ->getMock();

        $customerEmail = null;
        $quoteMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'getPayment', 'getCustomer', 'reserveOrderId', 'getId', 'getCustomerEmail', 'setCustomerEmail'])->getMock();
        $quoteMock->method('getShippingAddress')->willReturn($shippingAddress);
        $quoteMock->method('getBillingAddress')->willReturn($billingAddress);
        $quoteMock->method('getPayment')->willReturn($payment);
        $quoteMock->method('getId')->willReturn(1);
        $quoteMock->method('getCustomerEmail')->willReturnCallback(function () use (&$customerEmail) {
            return $customerEmail;
        });
        $quoteMock->method('setCustomerEmail')->willReturnCallback(
            function ($value) use (&$customerEmail, $quoteMock) {
                $customerEmail = $value;
                return $quoteMock;
            }
        );

        $maskedMock = $this->getFakeMock(MaskedQuoteIdToQuoteId::class)
            ->onlyMethods(['execute'])->getMock();
        $maskedMock->method('execute')->willReturn(1);

        // Records what each save() persisted, and where placeOrder() happened
        $events = [];
        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repoMock->method('get')->willReturn($quoteMock);
        $repoMock->expects($this->exactly(2))->method('save')->with($quoteMock)->willReturnCallback(
            function () use (&$events, &$shippingState, &$customerEmail) {
                $events[] = ['save', $shippingState['Firstname'] ?? null, $customerEmail];
            }
        );

        $sessionMock = $this->getFakeMock(CustomerSession::class)
            ->onlyMethods(['isLoggedIn'])->getMock();
        $sessionMock->method('isLoggedIn')->willReturn(false);

        $quoteMgmtMock = $this->getFakeMock(CartManagementInterface::class)->getMock();
        $quoteMgmtMock->expects($this->once())->method('placeOrder')->with(1)
            ->willReturnCallback(function () use (&$events) {
                $events[] = ['placeOrder'];
                throw new \RuntimeException('placeOrder failed');
            });

        $instance = $this->makeOrderCreate([
            'customerSession'        => $sessionMock,
            'maskedQuoteIdToQuoteId' => $maskedMock,
            'quoteRepository'        => $repoMock,
            'quoteManagement'        => $quoteMgmtMock,
        ]);

        try {
            $this->callMethod($instance, 'createOrder', ['paypal-order-id-123', 'masked-cart-id']);
            $this->fail('The placeOrder() failure must be rethrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('placeOrder failed', $e->getMessage());
        }

        // Placeholders were saved before placing the order, and the cleared quote was saved after the failure
        $this->assertSame(
            [
                ['save', 'PayPal', 'pending@paypal.customer'],
                ['placeOrder'],
                ['save', '', ''],
            ],
            $events
        );
        $this->assertSame(
            ['Firstname' => '', 'Lastname' => '', 'Street' => [], 'Telephone' => '', 'Email' => ''],
            $shippingState
        );
        // Real customer data on the billing address survives the cleanup
        $this->assertSame(
            ['Firstname' => 'Jane', 'Street' => ['Hoofdstraat 1'], 'Lastname' => '', 'Telephone' => '', 'Email' => ''],
            $billingState
        );
        $this->assertSame('', $customerEmail);
    }

    // -------------------------------------------------------------------------
    // getQuote() honours the ChangeQuoteControl decision for the resolved cart
    // -------------------------------------------------------------------------

    public function testGetQuoteReturnsQuoteWhenChangeQuoteControlAllows(): void
    {
        $quoteMock = $this->getFakeMock(Quote::class)->getMock();

        $maskedMock = $this->getFakeMock(MaskedQuoteIdToQuoteId::class)->getMock();
        $maskedMock->method('execute')->willReturn(42);

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repoMock->method('get')->with(42)->willReturn($quoteMock);

        $instance = $this->makeOrderCreate([
            'maskedQuoteIdToQuoteId' => $maskedMock,
            'quoteRepository'        => $repoMock,
            'changeQuoteControl'     => $this->makeChangeQuoteControl(true),
        ]);

        $this->assertSame($quoteMock, $this->callMethod($instance, 'getQuote', ['masked-cart-id']));
    }

    public function testGetQuoteThrowsWhenChangeQuoteControlDenies(): void
    {
        $quoteMock = $this->getFakeMock(Quote::class)->getMock();

        $maskedMock = $this->getFakeMock(MaskedQuoteIdToQuoteId::class)->getMock();
        $maskedMock->method('execute')->willReturn(42);

        $repoMock = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repoMock->method('get')->with(42)->willReturn($quoteMock);

        $instance = $this->makeOrderCreate([
            'maskedQuoteIdToQuoteId' => $maskedMock,
            'quoteRepository'        => $repoMock,
            'changeQuoteControl'     => $this->makeChangeQuoteControl(false),
        ]);

        $this->expectException(NoSuchEntityException::class);
        $this->callMethod($instance, 'getQuote', ['masked-cart-id']);
    }

    public static function missingCartIdProvider(): array
    {
        return ['null' => [null], 'empty' => [''], 'only spaces' => ['   ']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingCartIdProvider')]
    public function testExecuteWithoutCartIdThrowsPaypalExpressException(?string $cartId): void
    {
        $quoteManagement = $this->getFakeMock(CartManagementInterface::class)->getMock();
        $quoteManagement->expects($this->never())->method('placeOrder');

        $instance = $this->makeOrderCreate(['quoteManagement' => $quoteManagement]);

        $this->expectException(PaypalExpressException::class);
        $instance->execute('PAYPAL-ORDER-1', $cartId);
    }
    // -------------------------------------------------------------------------
    // execute(): full flow
    // -------------------------------------------------------------------------

    /**
     * Address double for the full flow: contact fields already filled, location fields from $location.
     *
     * @param array $location
     * @return QuoteAddress
     */
    private function makeFilledAddress(array &$location): QuoteAddress
    {
        $address = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class)->getMock();
        $address->method('getFirstname')->willReturn('Jane');
        $address->method('getLastname')->willReturn('Doe');
        $address->method('getStreet')->willReturn(['Hoofdstraat 1']);
        $address->method('getTelephone')->willReturn('0612345678');
        $address->method('getEmail')->willReturn('jane@example.com');
        foreach (['City', 'Postcode', 'CountryId', 'RegionId', 'Region'] as $field) {
            $address->method('get' . $field)->willReturnCallback(fn() => $location[$field] ?? null);
            $address->method('set' . $field)->willReturnCallback(
                function ($value) use (&$location, $field, $address) {
                    $location[$field] = $value;
                    return $address;
                }
            );
        }

        return $address;
    }

    /**
     * @param QuoteAddress $shipping
     * @param QuoteAddress $billing
     * @param QuotePayment $payment
     * @param int|string   $customerId
     * @return Quote
     */
    private function makeFlowQuote(
        QuoteAddress $shipping,
        QuoteAddress $billing,
        QuotePayment $payment,
        $customerId = 0
    ): Quote {
        $customer = $this->getFakeMock(CustomerInterface::class)->getMock();
        $customer->method('getId')->willReturn($customerId);

        $quote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->onlyMethods([
                'getShippingAddress', 'getBillingAddress', 'getPayment', 'getCustomer',
                'reserveOrderId', 'getId', 'getCustomerEmail', 'setCustomerEmail',
            ])->getMock();
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getCustomer')->willReturn($customer);
        $quote->method('getId')->willReturn(15);
        $quote->method('getCustomerEmail')->willReturn('jane@example.com');

        return $quote;
    }

    /**
     * @param Quote $quote
     * @return CartRepositoryInterface
     */
    private function makeFlowRepository(Quote $quote): CartRepositoryInterface
    {
        $repo = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $repo->method('get')->with(15)->willReturn($quote);

        return $repo;
    }

    /**
     * @return MaskedQuoteIdToQuoteId
     */
    private function makeFlowMasked(): MaskedQuoteIdToQuoteId
    {
        $masked = $this->getFakeMock(MaskedQuoteIdToQuoteId::class)->onlyMethods(['execute'])->getMock();
        $masked->method('execute')->with('masked-cart')->willReturn(15);

        return $masked;
    }

    /**
     * @param bool       $loggedIn
     * @param int|string $customerId
     * @return CustomerSession
     */
    private function makeSession(bool $loggedIn, $customerId = 0): CustomerSession
    {
        $session = $this->getFakeMock(CustomerSession::class)->onlyMethods(['isLoggedIn', 'getCustomerId'])->getMock();
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $session->method('getCustomerId')->willReturn($customerId);

        return $session;
    }

    /**
     * Places the order and updates it with the PayPal data; location fields only the shipping
     * address has are copied to the billing address.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testExecutePlacesTheOrderAndUpdatesItWithPayPalData(): void
    {
        $shippingLocation = [
            'City' => 'Utrecht', 'Postcode' => '3511AB', 'CountryId' => 'NL', 'RegionId' => 7, 'Region' => 'Utrecht',
        ];
        $billingLocation = ['Postcode' => '1012AB'];
        $shipping = $this->makeFilledAddress($shippingLocation);
        $billing = $this->makeFilledAddress($billingLocation);
        $shipping->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        $billing->expects($this->once())->method('setShouldIgnoreValidation')->with(true);

        $payment = $this->getFakeMock(QuotePayment::class)->onlyMethods(['setAdditionalInformation'])->getMock();
        $payment->expects($this->once())->method('setAdditionalInformation')->with('express_order_id', 'PAYPAL-1');

        $quote = $this->makeFlowQuote($shipping, $billing, $payment, '42');
        $quote->expects($this->once())->method('reserveOrderId');

        $repo = $this->makeFlowRepository($quote);
        $repo->expects($this->once())->method('save')->with($quote);

        $quoteManagement = $this->getFakeMock(CartManagementInterface::class)->getMock();
        $quoteManagement->expects($this->once())->method('placeOrder')->with(15)->willReturn(300);

        $orderShipping = $this->getFakeMock(\Magento\Sales\Model\Order\Address::class)->getMock();
        $orderBilling = $this->getFakeMock(\Magento\Sales\Model\Order\Address::class)->getMock();
        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['getCustomerIsGuest', 'getShippingAddress', 'getBillingAddress', 'getIncrementId'])
            ->getMock();
        $order->method('getCustomerIsGuest')->willReturn(null);
        $order->method('getShippingAddress')->willReturn($orderShipping);
        $order->method('getBillingAddress')->willReturn($orderBilling);
        $order->method('getIncrementId')->willReturn('000000300');

        $orderRepository = $this->getFakeMock(OrderRepositoryInterface::class)->getMock();
        $orderRepository->method('get')->with(300)->willReturn($order);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $orderUpdate = $this->getFakeMock(OrderUpdate::class)->getMock();
        $orderUpdate->expects($this->exactly(2))->method('updateAddress')
            ->willReturnCallback(function ($address, $isGuest) use ($orderShipping, $orderBilling) {
                static $calls = 0;
                $this->assertSame([$orderShipping, $orderBilling][$calls++], $address);
                $this->assertFalse($isGuest);
                return $address;
            });
        $orderUpdate->expects($this->once())->method('updateEmail')->with($order);
        $orderUpdate->expects($this->once())->method('updateCustomerName')->with($order);
        $orderUpdateFactory = $this->getFakeMock(OrderUpdateFactory::class)->onlyMethods(['create'])->getMock();
        $orderUpdateFactory->method('create')->willReturn($orderUpdate);

        $checkoutSession = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2::class)
            ->onlyMethods([
                'setLastQuoteId', 'setLastSuccessQuoteId', 'setLastOrderId', 'setLastRealOrderId', 'setLastOrderStatus',
            ])->getMock();
        foreach (['setLastQuoteId', 'setLastSuccessQuoteId', 'setLastOrderId', 'setLastOrderStatus'] as $setter) {
            $checkoutSession->method($setter)->willReturnSelf();
        }
        $checkoutSession->expects($this->once())->method('setLastRealOrderId')->with('000000300')->willReturnSelf();

        $response = $this->getFakeMock(OrderCreateResponseInterface::class)->getMock();
        $responseFactory = $this->getFakeMock(OrderCreateResponseInterfaceFactory::class)
            ->onlyMethods(['create'])->getMock();
        $responseFactory->expects($this->once())->method('create')->with(['orderId' => '000000300'])
            ->willReturn($response);

        $instance = $this->makeOrderCreate([
            'responseFactory'        => $responseFactory,
            'quoteManagement'        => $quoteManagement,
            'maskedQuoteIdToQuoteId' => $this->makeFlowMasked(),
            'customerSession'        => $this->makeSession(true, '42'),
            'checkoutSession'        => $checkoutSession,
            'quoteRepository'        => $repo,
            'orderRepository'        => $orderRepository,
            'orderUpdateFactory'     => $orderUpdateFactory,
        ]);

        $this->assertSame($response, $instance->execute('PAYPAL-1', 'masked-cart'));
        $this->assertSame(
            ['Postcode' => '1012AB', 'City' => 'Utrecht', 'CountryId' => 'NL', 'RegionId' => 7, 'Region' => 'Utrecht'],
            $billingLocation
        );
        $this->assertSame('3511AB', $shippingLocation['Postcode']);
    }

    public function testExecuteRefusesACartOfAnotherCustomerBeforePlacingTheOrder(): void
    {
        $location = [];
        $payment = $this->getFakeMock(QuotePayment::class)->onlyMethods(['setAdditionalInformation'])->getMock();
        $quote = $this->makeFlowQuote($this->makeFilledAddress($location), $this->makeFilledAddress($location), $payment, 8);

        $quoteManagement = $this->getFakeMock(CartManagementInterface::class)->getMock();
        $quoteManagement->expects($this->never())->method('placeOrder');

        $instance = $this->makeOrderCreate([
            'quoteManagement'        => $quoteManagement,
            'maskedQuoteIdToQuoteId' => $this->makeFlowMasked(),
            'customerSession'        => $this->makeSession(true, 7),
            'quoteRepository'        => $this->makeFlowRepository($quote),
        ]);

        $this->expectException(PaypalExpressException::class);
        $instance->execute('PAYPAL-1', 'masked-cart');
    }
}
