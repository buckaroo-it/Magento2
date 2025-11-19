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

namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Quote;

use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Magento\Catalog\Model\Product;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\Store;
use Magento\Sales\Model\Order\Address;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RecreateTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Recreate::class;

    /** @var CartRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $cartRepository;

    /** @var Cart|\PHPUnit\Framework\MockObject\MockObject */
    private $cart;

    /** @var CheckoutSession|\PHPUnit\Framework\MockObject\MockObject */
    private $checkoutSession;

    /** @var QuoteFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $quoteFactory;

    /** @var ProductFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $productFactory;

    /** @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageManager;

    /** @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $storeManager;

    /** @var Log|\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = $this->getFakeMock(CartRepositoryInterface::class)->getMock();
        $this->cart = $this->getFakeMock(Cart::class)->getMock();
        $this->checkoutSession = $this->getFakeMock(CheckoutSession::class, false)
            ->addMethods(['unsLastRealOrderId', 'unsLastOrderId', 'unsLastSuccessQuoteId', 'unsRedirectUrl', 'unsLastQuoteId'])
            ->onlyMethods(['replaceQuote', 'setQuoteId'])
            ->getMock();
        $this->quoteFactory = $this->getFakeMock(QuoteFactory::class)->getMock();
        $this->productFactory = $this->getFakeMock(ProductFactory::class)->getMock();
        $this->messageManager = $this->getFakeMock(ManagerInterface::class)->getMock();
        $this->storeManager = $this->getFakeMock(StoreManagerInterface::class)->getMock();
        $this->logger = $this->getFakeMock(Log::class)->getMock();
    }

    public function testRecreateById()
    {
        $quoteId = 123;
        $storeId = 1;

        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        $newQuote = $this->getFakeMock(Quote::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        $this->quoteFactory->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);

        $oldQuote->method('load')
            ->with($quoteId)
            ->willReturnSelf();

        $oldQuote->expects($this->atLeastOnce())->method('getId')->willReturn($quoteId);
        $oldQuote->method('getStoreId')->willReturn($storeId);

        $this->storeManager->method('getStore')
            ->with($storeId)
            ->willReturn($store);

        $newQuote->method('merge')->with($oldQuote);
        $newQuote->method('setStore')->with($store);
        $newQuote->method('setIsActive')->with(true);
        $newQuote->method('collectTotals');
        $newQuote->method('setReservedOrderId')->with(null);

        $this->checkoutSession->method('replaceQuote')->with($newQuote);
        $this->checkoutSession->method('unsLastRealOrderId');
        $this->checkoutSession->method('unsLastOrderId');
        $this->checkoutSession->method('unsLastSuccessQuoteId');
        $this->checkoutSession->method('unsRedirectUrl');
        $this->checkoutSession->method('unsLastQuoteId');

        $this->cartRepository->method('save')->with($newQuote);

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'quoteFactory' => $this->quoteFactory,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('recreateById', [$quoteId], $instance);
        $this->assertEquals($newQuote, $result);
    }

    public function testRecreateByIdWithNonExistentQuote()
    {
        $quoteId = 999;

        $oldQuote = $this->getFakeMock(Quote::class)->getMock();

        $this->quoteFactory->method('create')
            ->willReturn($oldQuote);

        $oldQuote->method('load')
            ->with($quoteId)
            ->willReturnSelf();

        $oldQuote->method('getId')->willReturn(null);

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('recreateById', [$quoteId], $instance);
        $this->assertNull($result);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testDuplicate()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['load', 'getId', 'getPayment'])
            ->getMock();
        $newQuote = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['setStore', 'setStoreId', 'getBillingAddress', 'getShippingAddress', 'setIsActive', 'collectTotals', 'save', 'addProduct', 'setCustomerIsGuest', 'getPayment', 'getCustomerIsGuest'])
            ->addMethods(['setCustomerId', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname', 'getCustomerId', 'getCustomerEmail', 'getCustomerFirstname', 'getCustomerLastname'])
            ->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        $billingAddress = $this->getFakeMock(Address::class)->getMock();
        $shippingAddress = $this->getFakeMock(Address::class)->getMock();
        $quoteBillingAddress = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->addMethods(['importOrderAddress'])
            ->getMock();
        $quoteShippingAddress = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)
            ->addMethods(['importOrderAddress', 'setShippingMethod', 'setCollectShippingRates'])
            ->getMock();

        // Setup order data
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->expects($this->atLeastOnce())->method('getStore')->willReturn($store);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('test@example.com');
        $order->method('getCustomerFirstname')->willReturn('John');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');

        // Setup order items
        $orderItem = $this->getFakeMock(Item::class)->getMock();
        $orderItem->method('getProductId')->willReturn(1);
        $orderItem->method('getQtyOrdered')->willReturn(2);
        $orderItem->method('getProductOptionByCode')->with('info_buyRequest')->willReturn(['qty' => 2]);

        $order->method('getAllVisibleItems')->willReturn([$orderItem]);

        // Setup quotes
        $this->quoteFactory->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);

        $oldQuotePayment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod'])
            ->getMock();
        $oldQuotePayment->method('getMethod')->willReturn('buckaroo_magento2_ideal');

        $oldQuote->method('load')->with(123)->willReturnSelf();
        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getPayment')->willReturn($oldQuotePayment);

        // Setup store manager
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->storeManager->method('setCurrentStore')->with($store);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        // Setup new quote
        $newQuote->method('setStore')->with($store);
        $newQuote->method('setStoreId')->with(1);
        $newQuote->method('setCustomerId')->with(1);
        $newQuote->method('setCustomerEmail')->with('test@example.com');
        $newQuote->method('setCustomerFirstname')->with('John');
        $newQuote->method('setCustomerLastname')->with('Doe');
        $newQuote->method('setCustomerIsGuest')->with(false);
        $newQuote->method('getBillingAddress')->willReturn($quoteBillingAddress);
        $newQuote->method('getShippingAddress')->willReturn($quoteShippingAddress);

        $newQuotePayment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['setMethod', 'setQuote'])
            ->getMock();
        $newQuote->method('getPayment')->willReturn($newQuotePayment);
        $newQuote->method('setIsActive')->with(true);
        $newQuote->method('collectTotals');
        $newQuote->method('save');

        // Setup product
        $product = $this->getFakeMock(Product::class)->getMock();
        $product->method('getId')->willReturn(1);

        $this->productFactory->method('create')
            ->willReturn($product);

        $product->method('load')->with(1)->willReturnSelf();

        $newQuote->method('addProduct')
            ->with($product, $this->isInstanceOf(\Magento\Framework\DataObject::class));

        // Setup addresses
        $quoteBillingAddress->method('importOrderAddress')->with($billingAddress);
        $quoteShippingAddress->method('importOrderAddress')->with($shippingAddress);
        $quoteShippingAddress->method('setShippingMethod')->with('flatrate_flatrate');
        $quoteShippingAddress->method('setCollectShippingRates')->with(true);

        // Setup checkout session
        $this->checkoutSession->method('replaceQuote')->with($newQuote);
        $this->checkoutSession->method('setQuoteId');

        // Configure getters to return expected values
        $newQuote->method('getCustomerId')->willReturn(1);
        $newQuote->method('getCustomerEmail')->willReturn('test@example.com');
        $newQuote->method('getCustomerFirstname')->willReturn('John');
        $newQuote->method('getCustomerLastname')->willReturn('Doe');
        $newQuote->method('getCustomerIsGuest')->willReturn(false);

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'productFactory' => $this->productFactory,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertEquals($newQuote, $result);
        $this->assertEquals(1, $result->getCustomerId());
        $this->assertEquals('test@example.com', $result->getCustomerEmail());
        $this->assertEquals('John', $result->getCustomerFirstname());
        $this->assertEquals('Doe', $result->getCustomerLastname());
        $this->assertFalse($result->getCustomerIsGuest());
    }

    public function testDuplicateWithGuestCustomer()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        $newQuote = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['setStore', 'setStoreId', 'getBillingAddress', 'getShippingAddress', 'setIsActive', 'collectTotals', 'save', 'setCustomerIsGuest', 'getCustomerIsGuest'])
            ->addMethods(['setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname', 'getCustomerId', 'getCustomerEmail', 'getCustomerFirstname', 'getCustomerLastname'])
            ->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        // Setup guest order
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->expects($this->atLeastOnce())->method('getStore')->willReturn($store);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null); // Guest customer
        $order->method('getCustomerEmail')->willReturn('guest@example.com');
        $order->method('getCustomerFirstname')->willReturn('Guest');
        $order->method('getCustomerLastname')->willReturn('User');
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->quoteFactory->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);

        $oldQuote->method('load')->with(123)->willReturnSelf();
        $oldQuote->method('getId')->willReturn(123);

        // Setup store manager
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->storeManager->method('setCurrentStore')->with($store);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        // Setup guest quote
        $newQuote->method('setStore')->with($store);
        $newQuote->method('setStoreId')->with(1);
        $newQuote->method('setCustomerEmail')->with('guest@example.com');
        $newQuote->method('setCustomerFirstname')->with('Guest');
        $newQuote->method('setCustomerLastname')->with('User');
        $newQuote->method('setCustomerIsGuest')->with(true);
        $newQuote->method('setIsActive')->with(true);
        $newQuote->method('collectTotals');
        $newQuote->method('save');

        // Configure getters to return expected values
        $newQuote->method('getCustomerId')->willReturn(null);
        $newQuote->method('getCustomerEmail')->willReturn('guest@example.com');
        $newQuote->method('getCustomerFirstname')->willReturn('Guest');
        $newQuote->method('getCustomerLastname')->willReturn('User');
        $newQuote->method('getCustomerIsGuest')->willReturn(true);

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'productFactory' => $this->productFactory,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertEquals($newQuote, $result);
        $this->assertNull($result->getCustomerId());
        $this->assertEquals('guest@example.com', $result->getCustomerEmail());
        $this->assertEquals('Guest', $result->getCustomerFirstname());
        $this->assertEquals('User', $result->getCustomerLastname());
        $this->assertTrue($result->getCustomerIsGuest());
    }

    public function testDuplicateWithMissingOriginalQuote()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();

        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(999);

        $this->quoteFactory->method('create')
            ->willReturn($oldQuote);

        $oldQuote->method('load')->with(999)->willReturnSelf();
        $oldQuote->method('getId')->willReturn(null);

        $this->logger->method('addError')
            ->with($this->stringContains('Original quote not found'));

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertNull($result);
    }

    public function testDuplicateWithException()
    {
        $order = $this->getFakeMock(Order::class)->getMock();

        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);

        $this->quoteFactory->method('create')
            ->willThrowException(new \Exception('Test exception'));

        $this->logger->method('addError')
            ->with($this->stringContains('Error duplicating order to quote'));

        $this->messageManager->method('addErrorMessage');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertNull($result);
    }
}
