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
        $this->checkoutSession = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2::class, false)
            ->onlyMethods(['replaceQuote', 'setQuoteId', 'getQuoteId', 'unsLastRealOrderId', 'unsLastOrderId', 'unsLastSuccessQuoteId', 'unsRedirectUrl', 'unsLastQuoteId'])->getMock();
        // The failed order's quote is the shopper's current cart, as after a failed payment
        $this->checkoutSession->method('getQuoteId')->willReturn(123);
        $this->quoteFactory = $this->getFakeMock(QuoteFactory::class)->getMock();
        $this->productFactory = $this->getFakeMock(ProductFactory::class)->getMock();
        $this->messageManager = $this->getFakeMock(ManagerInterface::class)->getMock();
        $this->storeManager = $this->getFakeMock(StoreManagerInterface::class)->getMock();
        $this->logger = $this->getFakeMock(Log::class)->getMock();
    }

    public function testRecreateByIdForRegisteredCustomer()
    {
        $oldQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['getId', 'getStoreId', 'getCustomerId', 'getCustomerEmail', 'getCustomerFirstname', 'getCustomerLastname'])
            ->getMock();
        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getStoreId')->willReturn('2');
        $oldQuote->method('getCustomerId')->willReturn(7);
        $oldQuote->method('getCustomerEmail')->willReturn('jane@example.com');
        $oldQuote->method('getCustomerFirstname')->willReturn('Jane');
        $oldQuote->method('getCustomerLastname')->willReturn('Roe');

        $log = [];
        $newQuote = $this->createRecreatedQuoteMock($oldQuote, 2, $log);
        $newQuote->expects($this->once())->method('setCustomerId')->with(7);
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('jane@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('Jane');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('Roe');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(false);

        $result = $this->runRecreateById($oldQuote, $newQuote, $log);

        $this->assertSame($newQuote, $result);
        $this->assertSame($this->expectedRecreateByIdCalls(), $log);
        $this->assertSame('1', $newQuote->getData('trigger_recollect'));
    }

    public function testRecreateByIdForGuest()
    {
        $oldQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['getId', 'getStoreId', 'getCustomerId', 'getCustomerEmail'])
            ->getMock();
        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getStoreId')->willReturn(3);
        $oldQuote->method('getCustomerId')->willReturn(null);
        $oldQuote->method('getCustomerEmail')->willReturn('guest@example.com');

        $log = [];
        $newQuote = $this->createRecreatedQuoteMock($oldQuote, 3, $log);
        $newQuote->expects($this->never())->method('setCustomerId');
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('guest@example.com');
        $newQuote->expects($this->never())->method('setCustomerFirstname');
        $newQuote->expects($this->never())->method('setCustomerLastname');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(true);

        $result = $this->runRecreateById($oldQuote, $newQuote, $log);

        $this->assertSame($newQuote, $result);
        $this->assertSame($this->expectedRecreateByIdCalls(), $log);
    }

    public function testRecreateByIdWithNonExistentQuote()
    {
        $quoteId = 999;

        $this->cartRepository->method('get')
            ->with($quoteId)
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('No such quote')));
        $this->cartRepository->expects($this->never())->method('save');
        $this->quoteFactory->expects($this->never())->method('create');
        $this->checkoutSession->expects($this->never())->method('replaceQuote');
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with('No such quote');

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->recreateById($quoteId);
        $this->assertNull($result);
    }

    public function testRecreateByIdWithoutQuoteIdCreatesNothing()
    {
        $oldQuote = $this->getFakeMock(Quote::class, false)->onlyMethods(['getId'])->getMock();
        $oldQuote->method('getId')->willReturn(null);

        $this->cartRepository->method('get')->with(55)->willReturn($oldQuote);
        $this->cartRepository->expects($this->never())->method('save');
        $this->quoteFactory->expects($this->never())->method('create');
        $this->checkoutSession->expects($this->never())->method('replaceQuote');

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'quoteFactory' => $this->quoteFactory,
            'logger' => $this->logger,
        ]);

        $this->assertNull($instance->recreateById('55'));
    }

    /**
     * Build the new quote double and record the calls whose order matters
     *
     * @param Quote $oldQuote
     * @param int $storeId
     * @param array $log
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createRecreatedQuoteMock($oldQuote, int $storeId, array &$log)
    {
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods([
                'setCustomerId', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname',
                'setCustomerIsGuest', 'merge', 'setStoreId', 'setIsActive', 'collectTotals', 'setReservedOrderId',
            ])
            ->getMock();

        $record = function (string $name) use (&$log, $newQuote) {
            return function () use (&$log, $name, $newQuote) {
                $log[] = $name;
                return $newQuote;
            };
        };

        $newQuote->expects($this->once())->method('merge')
            ->with($this->identicalTo($oldQuote))->willReturnCallback($record('merge'));
        $newQuote->expects($this->once())->method('setStoreId')
            ->with($this->identicalTo($storeId))->willReturnCallback($record('setStoreId'));
        $newQuote->expects($this->exactly(2))->method('setIsActive')
            ->with(true)->willReturnCallback($record('setIsActive'));
        $newQuote->expects($this->once())->method('collectTotals')->willReturnCallback($record('collectTotals'));
        $newQuote->expects($this->once())->method('setReservedOrderId')
            ->with(null)->willReturnCallback($record('setReservedOrderId'));

        return $newQuote;
    }

    /**
     * Run recreateById with session and repository doubles that record their calls
     *
     * @param Quote $oldQuote
     * @param Quote $newQuote
     * @param array $log
     * @return mixed
     */
    private function runRecreateById($oldQuote, $newQuote, array &$log)
    {
        $record = function (string $name) use (&$log) {
            return function () use (&$log, $name) {
                $log[] = $name;
                return null;
            };
        };

        $this->quoteFactory->expects($this->once())->method('create')->willReturn($newQuote);
        $this->cartRepository->expects($this->once())->method('get')->with(123)->willReturn($oldQuote);
        $this->cartRepository->expects($this->exactly(2))->method('save')
            ->with($this->identicalTo($newQuote))->willReturnCallback($record('save'));
        $this->checkoutSession->expects($this->once())->method('replaceQuote')
            ->with($this->identicalTo($newQuote))->willReturnCallback($record('replaceQuote'));
        foreach (['unsLastRealOrderId', 'unsLastOrderId', 'unsLastSuccessQuoteId', 'unsRedirectUrl', 'unsLastQuoteId'] as $method) {
            $this->checkoutSession->expects($this->once())->method($method)->willReturnCallback($record($method));
        }
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        return $instance->recreateById('123');
    }

    /**
     * Calls recreateById makes once the customer data is copied, in order
     *
     * @return string[]
     */
    private function expectedRecreateByIdCalls(): array
    {
        return [
            'merge',
            'setStoreId',
            'setIsActive',
            'collectTotals',
            'save',
            'setIsActive',
            'setReservedOrderId',
            'replaceQuote',
            'unsLastRealOrderId',
            'unsLastOrderId',
            'unsLastSuccessQuoteId',
            'unsRedirectUrl',
            'unsLastQuoteId',
            'save',
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testDuplicate()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class, false)
            ->onlyMethods(['load', 'getId', 'getPayment', 'setIsActive', 'save'])
            ->getMock();
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['setStoreId', 'getBillingAddress', 'getShippingAddress', 'setIsActive', 'collectTotals', 'save', 'addProduct', 'setCustomerIsGuest', 'getPayment', 'setCustomerId', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname'])->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        $billingAddress = $this->getFakeMock(Address::class)->getMock();
        $shippingAddress = $this->getFakeMock(Address::class)->getMock();
        $quoteBillingAddress = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class)
            ->onlyMethods(['importOrderAddress'])->getMock();
        $quoteShippingAddress = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class)
            ->onlyMethods(['importOrderAddress', 'setShippingMethod', 'setCollectShippingRates'])->getMock();

        // Setup order data
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->method('getStore')->willReturn($store);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('test@example.com');
        $order->method('getCustomerFirstname')->willReturn('John');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');

        // Setup order items
        // The stored buyRequest keeps the qty of the first add-to-cart, not the ordered qty.
        $orderItem = $this->getFakeMock(Item::class)
            ->onlyMethods(['getProductId', 'getQtyOrdered', 'getProductOptionByCode'])
            ->getMock();
        $orderItem->method('getProductId')->willReturn(1);
        $orderItem->method('getQtyOrdered')->willReturn(3.0);
        $orderItem->method('getProductOptionByCode')
            ->with('info_buyRequest')
            ->willReturn(['product' => 1, 'qty' => 1]);

        $order->method('getAllVisibleItems')->willReturn([$orderItem]);

        // Setup quotes
        $this->quoteFactory->method('create')
            ->willReturn($newQuote);

        $this->cartRepository->method('get')->with(123)->willReturn($oldQuote);

        $oldQuotePayment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['getMethod'])
            ->getMock();
        $oldQuotePayment->method('getMethod')->willReturn('buckaroo_magento2_ideal');

        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getPayment')->willReturn($oldQuotePayment);
        $oldQuote->expects($this->once())->method('setIsActive')->with(false)->willReturnSelf();
        $oldQuote->expects($this->never())->method('save');

        // Setup store manager
        $this->storeManager->expects($this->once())->method('getStore')->with(1)->willReturn($store);
        $this->storeManager->expects($this->once())->method('setCurrentStore')->with($this->identicalTo($store));
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        // Customer data comes from the order
        $newQuote->expects($this->once())->method('setStoreId')->with($this->identicalTo(1));
        $newQuote->expects($this->once())->method('setCustomerId')->with(1);
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('test@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('John');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('Doe');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(false);
        $newQuote->method('getBillingAddress')->willReturn($quoteBillingAddress);
        $newQuote->method('getShippingAddress')->willReturn($quoteShippingAddress);

        $newQuotePayment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class, false)
            ->onlyMethods(['setMethod', 'setQuote'])
            ->getMock();
        $newQuote->method('getPayment')->willReturn($newQuotePayment);
        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');
        $newQuote->expects($this->never())->method('save');

        // Setup product
        $product = $this->getFakeMock(Product::class)->getMock();
        $product->method('getId')->willReturn(1);

        $productRepositoryMock = $this->getFakeMock(\Magento\Catalog\Api\ProductRepositoryInterface::class)
            ->getMock();
        $productRepositoryMock->method('getById')->with(1)->willReturn($product);

        $newQuote->expects($this->once())->method('addProduct')
            ->with(
                $product,
                $this->callback(function ($buyRequest) {
                    return $buyRequest instanceof \Magento\Framework\DataObject
                        && $buyRequest->getQty() == 3
                        && $buyRequest->getProduct() == 1;
                })
            );

        // Setup addresses
        $quoteBillingAddress->method('importOrderAddress')->with($billingAddress);
        $quoteShippingAddress->method('importOrderAddress')->with($shippingAddress);
        $quoteShippingAddress->method('setShippingMethod')->with('flatrate_flatrate');
        $quoteShippingAddress->method('setCollectShippingRates')->with(true);

        // Setup checkout session
        $this->checkoutSession->expects($this->once())->method('replaceQuote')->with($this->identicalTo($newQuote));
        $this->checkoutSession->expects($this->once())->method('setQuoteId');

        // The new quote is saved twice, then the original quote once it is deactivated
        $saved = [];
        $this->cartRepository->expects($this->exactly(3))->method('save')->willReturnCallback(
            function ($quote) use (&$saved) {
                $saved[] = $quote;
                return null;
            }
        );

        // BTI-1913 / BTI-1914: the shopper's current carts are merged, then replaced by the restored cart
        $previousCart = $this->getFakeMock(Quote::class)->getMock();
        $cartMerger = $this->getFakeMock(\Buckaroo\Magento2\Service\Sales\Quote\SecondChanceCartMerger::class)
            ->getMock();
        $cartMerger->expects($this->once())->method('mergeInto')
            ->with($newQuote, $order, 123)
            ->willReturn([$previousCart]);
        $cartMerger->expects($this->once())->method('deactivate')->with([$previousCart]);

        $instance = $this->getInstance([
            'cartMerger' => $cartMerger,
            'quoteFactory' => $this->quoteFactory,
            'cartRepository' => $this->cartRepository,
            'productRepository' => $productRepositoryMock,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->duplicate($order);
        $this->assertSame($newQuote, $result);
        $this->assertSame([$newQuote, $newQuote, $oldQuote], $saved);
    }

    /**
     * BTI-1911: products that cannot be added are named in a notice instead of being left out silently
     */
    public function testDuplicateNamesProductsThatAreNoLongerAvailable()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['setStore', 'setStoreId', 'setIsActive', 'collectTotals', 'save', 'addProduct', 'setCustomerIsGuest', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname'])
            ->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $items = [];
        foreach ([1 => 'Joust Duffle Bag', 2 => 'Deleted Bag', 3 => 'Sold Out Bag'] as $productId => $name) {
            $item = $this->getFakeMock(Item::class)
                ->onlyMethods(['getProductId', 'getName', 'getBuyRequest'])
                ->getMock();
            $item->method('getProductId')->willReturn($productId);
            $item->method('getName')->willReturn($name);
            $item->method('getBuyRequest')->willReturn(new \Magento\Framework\DataObject(['qty' => 1]));
            $items[] = $item;
        }
        $order->method('getAllVisibleItems')->willReturn($items);

        $available = $this->getFakeMock(Product::class)->onlyMethods(['getSku', 'setCustomOptions'])->getMock();
        $available->method('getSku')->willReturn('24-MB01');
        $soldOut = $this->getFakeMock(Product::class)->onlyMethods(['getSku', 'setCustomOptions'])->getMock();
        $soldOut->method('getSku')->willReturn('24-MB03');
        $productRepository = $this->getFakeMock(\Magento\Catalog\Api\ProductRepositoryInterface::class)->getMock();
        $productRepository->method('getById')->willReturnCallback(
            function ($productId) use ($available, $soldOut) {
                if ($productId === 2) {
                    throw new \Magento\Framework\Exception\NoSuchEntityException(__('Not found'));
                }
                return $productId === 3 ? $soldOut : $available;
            }
        );
        $newQuote->method('addProduct')->willReturnCallback(
            function ($product) {
                return $product->getSku() === '24-MB03' ? 'Product that you are trying to add is not available.' : null;
            }
        );

        $this->quoteFactory->method('create')->willReturn($newQuote);
        $this->cartRepository->method('get')->willThrowException(
            new \Magento\Framework\Exception\NoSuchEntityException(__('Gone'))
        );
        $this->storeManager->method('getStore')->willReturn($store);

        $this->messageManager->expects($this->once())->method('addWarningMessage')->with(
            __(
                'Some products from your order are no longer available and were not added to your cart: %1',
                'Deleted Bag, Sold Out Bag'
            )
        );

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'cartRepository' => $this->cartRepository,
            'productRepository' => $productRepository,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $this->assertSame($newQuote, $instance->duplicate($order));
    }

    public function testDuplicateWithGuestCustomer()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['setStoreId', 'getBillingAddress', 'getShippingAddress', 'setIsActive', 'collectTotals', 'save', 'setCustomerIsGuest', 'setCustomerId', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname'])->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        // Setup guest order
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->method('getStore')->willReturn($store);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null); // Guest customer
        $order->method('getCustomerEmail')->willReturn('guest@example.com');
        $order->method('getCustomerFirstname')->willReturn('Guest');
        $order->method('getCustomerLastname')->willReturn('User');
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->quoteFactory->method('create')
            ->willReturn($newQuote);

        $this->cartRepository->method('get')->with(123)->willReturn($oldQuote);

        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->expects($this->once())->method('setIsActive')->with(false)->willReturnSelf();
        $oldQuote->expects($this->never())->method('save');

        // Setup store manager
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->storeManager->method('setCurrentStore')->with($store);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        // Guest data comes from the order, without a customer id
        $newQuote->expects($this->once())->method('setStoreId')->with($this->identicalTo(1));
        $newQuote->expects($this->never())->method('setCustomerId');
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('guest@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('Guest');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('User');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(true);
        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');

        $saved = [];
        $this->cartRepository->expects($this->exactly(3))->method('save')->willReturnCallback(
            function ($quote) use (&$saved) {
                $saved[] = $quote;
                return null;
            }
        );
        $this->checkoutSession->expects($this->once())->method('replaceQuote')->with($this->identicalTo($newQuote));

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->duplicate($order);
        $this->assertSame($newQuote, $result);
        $this->assertSame([$newQuote, $newQuote, $oldQuote], $saved);
    }

    public function testDuplicateKeepsGuestQuoteThatBecameAccountCart()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        // The guest signed in afterwards, so the guest quote is now their account cart
        $oldQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['getId', 'getCustomerId', 'setIsActive', 'save', 'getPayment'])
            ->getMock();
        $oldQuote->method('getCustomerId')->willReturn(42);
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'collectTotals', 'save'])->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        // Setup guest order
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);
        $order->method('getStore')->willReturn($store);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null); // Guest customer
        $order->method('getCustomerEmail')->willReturn('guest@example.com');
        $order->method('getCustomerFirstname')->willReturn('Guest');
        $order->method('getCustomerLastname')->willReturn('User');
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->quoteFactory->method('create')
            ->willReturn($newQuote);

        $this->cartRepository->method('get')->with(123)->willReturn($oldQuote);

        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->expects($this->never())->method('setIsActive');

        // Setup store manager
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->storeManager->method('setCurrentStore')->with($store);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        // Only the new quote is saved; the account cart is left untouched
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->identicalTo($newQuote));

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->duplicate($order);
        $this->assertSame($newQuote, $result);
    }

    public function testDuplicateWithMissingOriginalQuote()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $newQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class, false)
            ->onlyMethods(['setStoreId', 'getBillingAddress', 'getShippingAddress', 'setIsActive', 'collectTotals', 'save', 'setCustomerIsGuest', 'setCustomerId', 'setCustomerEmail', 'setCustomerFirstname', 'setCustomerLastname'])->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();

        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(999);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getCustomerEmail')->willReturn('guest@example.com');
        $order->method('getCustomerFirstname')->willReturn('Guest');
        $order->method('getCustomerLastname')->willReturn('User');
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->quoteFactory->method('create')->willReturn($newQuote);
        $this->cartRepository->method('get')
            ->with(999)
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($newQuote);

        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $store->method('getCode')->willReturn('default');
        $store->method('getConfig')->with('general/locale/code')->willReturn('en_US');

        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');
        $newQuote->expects($this->never())->method('setCustomerId');
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('guest@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('Guest');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('User');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(true);
        $this->checkoutSession->expects($this->once())->method('replaceQuote')->with($this->identicalTo($newQuote));

        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'storeManager' => $this->storeManager,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->duplicate($order);
        $this->assertSame($newQuote, $result);
    }

    public function testDuplicateWithException()
    {
        $order = $this->getFakeMock(Order::class)->getMock();

        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn(123);

        $this->cartRepository->method('get')
            ->with(123)
            ->willThrowException(new \Exception('Test exception'));

        $this->logger->expects($this->once())->method('addError')
            ->with('Error duplicating order to quote: Test exception');
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with('Test exception');
        $this->quoteFactory->expects($this->never())->method('create');
        $this->checkoutSession->expects($this->never())->method('replaceQuote');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'checkoutSession' => $this->checkoutSession,
            'cartRepository' => $this->cartRepository,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $result = $instance->duplicate($order);
        $this->assertNull($result);
    }

    public function testRecreateReactivatesTheQuoteAndClearsTheBuckarooFee()
    {
        $quote = $this->getFakeMock(Quote::class)->onlyMethods(['save'])->getMock();
        $quote->setData([
            'is_active' => false,
            'reserved_order_id' => '000000001',
            'buckaroo_fee' => 2.5,
            'base_buckaroo_fee' => 2.5,
            'buckaroo_fee_tax_amount' => 0.5,
            'buckaroo_fee_base_tax_amount' => 0.5,
            'buckaroo_fee_incl_tax' => 3.0,
            'base_buckaroo_fee_incl_tax' => 3.0,
        ]);

        $instance = $this->getInstance(['logger' => $this->logger]);

        $this->assertSame($quote, $instance->recreate($quote));
        $this->assertTrue($quote->getData('is_active'));
        $this->assertSame('1', $quote->getData('trigger_recollect'));
        foreach ([
            'reserved_order_id', 'buckaroo_fee', 'base_buckaroo_fee', 'buckaroo_fee_tax_amount',
            'buckaroo_fee_base_tax_amount', 'buckaroo_fee_incl_tax', 'base_buckaroo_fee_incl_tax',
        ] as $field) {
            $this->assertNull($quote->getData($field), $field);
        }
    }

    public function testRecreateByIdWithoutIdLooksUpNoQuote()
    {
        $this->cartRepository->expects($this->once())->method('get')->with($this->identicalTo(0))
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('No such entity')));
        $this->quoteFactory->expects($this->never())->method('create');

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $this->assertNull($instance->recreateById(null));
    }

    public function testDuplicateCopiesTheOrderAddressesAndShippingMethod()
    {
        $scenario = $this->buildDuplicateScenario([
            'billing' => $this->createOrderAddress('Jane', 'Utrecht', 'NL'),
            'shipping' => $this->createOrderAddress('John', 'Gent', 'BE'),
            'shippingMethod' => 'flatrate_flatrate',
            'shippingAmount' => 5.0,
            'baseShippingAmount' => 4.5,
        ]);
        $scenario['shippingAddress']->expects($this->once())->method('setCollectShippingRates')->with(true);
        $scenario['shippingAddress']->expects($this->once())->method('setShippingMethod')->with('flatrate_flatrate');

        $this->assertSame($scenario['quote'], $scenario['instance']->duplicate($scenario['order']));

        $billing = $scenario['billingAddress'];
        $this->assertSame(
            ['Jane', 'Roe', 'Acme', 'Main 1', 'Utrecht', 12, 'Utrecht', '1234AB', 'NL', '0612', '0613',
                'jane@example.com'],
            array_map([$billing, 'getData'], [
                'firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'region', 'postcode',
                'country_id', 'telephone', 'fax', 'email',
            ])
        );
        $shipping = $scenario['shippingAddress'];
        $this->assertSame(['John', 'Gent', 'BE'], array_map([$shipping, 'getData'], ['firstname', 'city', 'country_id']));
        $this->assertSame(5.0, $shipping->getShippingAmount());
        $this->assertSame(4.5, $shipping->getBaseShippingAmount());
    }

    public function testDuplicateRestoresCouponAndGiftCardsOfTheOrder()
    {
        $scenario = $this->buildDuplicateScenario([
            'coupon' => 'SAVE10',
            'data' => ['gift_cards' => '[{"c":"GC1"}]', 'gift_cards_amount' => '10.0000', 'base_gift_cards_amount' => '9.0000'],
        ]);
        // All gift cards are restored, so the configured notice is not shown
        $scenario['secondChanceConfig']->method('getGiftCardInvalidMessage')->willReturn('A gift card could not be applied.');
        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $scenario['instance']->duplicate($scenario['order']);

        $quote = $scenario['quote'];
        $this->assertSame('SAVE10', $quote->getData('coupon_code'));
        $this->assertSame('[{"c":"GC1"}]', $quote->getData('gift_cards'));
        $this->assertSame('10.0000', $quote->getData('gift_cards_amount'));
        $this->assertSame('9.0000', $quote->getData('base_gift_cards_amount'));
    }

    public function testDuplicateWithoutCouponOrGiftCardsSetsNone()
    {
        $scenario = $this->buildDuplicateScenario([]);

        $scenario['instance']->duplicate($scenario['order']);

        $this->assertNull($scenario['quote']->getData('coupon_code'));
        $this->assertNull($scenario['quote']->getData('gift_cards'));
    }

    public function testDuplicateWarnsWhenGiftCardsCannotBeFullyRestored()
    {
        $scenario = $this->buildDuplicateScenario([
            'data' => ['gift_cards' => '[{"c":"GC1"}]', 'gift_cards_amount' => '10.0000'],
            'restoredGiftCardsAmount' => 4.0,
        ]);
        $scenario['secondChanceConfig']->expects($this->once())->method('getGiftCardInvalidMessage')
            ->with(1)->willReturn('A gift card could not be applied.');
        $this->messageManager->expects($this->once())->method('addWarningMessage')
            ->with(__('A gift card could not be applied.'));

        $scenario['instance']->duplicate($scenario['order']);
    }

    public function testDuplicateWithoutSessionCartOrOrderQuoteStillRestores()
    {
        $this->checkoutSession = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\SessionStub2::class, false)
            ->onlyMethods(['replaceQuote', 'setQuoteId', 'getQuoteId'])->getMock();
        $this->checkoutSession->method('getQuoteId')->willReturn(null);
        $scenario = $this->buildDuplicateScenario(['orderQuoteId' => null]);
        $scenario['cartMerger']->expects($this->once())->method('mergeInto')
            ->with($scenario['quote'], $scenario['order'], 0)->willReturn([]);
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $this->assertSame($scenario['quote'], $scenario['instance']->duplicate($scenario['order']));
    }

    public function testDuplicateDeactivatesTheOriginalQuoteOfTheSameCustomer()
    {
        // Customer IDs loaded from the database are strings
        $oldQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->onlyMethods(['getId', 'getCustomerId', 'setIsActive', 'getPayment'])->getMock();
        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getCustomerId')->willReturn('7');
        $oldQuote->expects($this->once())->method('setIsActive')->with(false);
        $scenario = $this->buildDuplicateScenario(['customerId' => '7', 'oldQuote' => $oldQuote]);

        $scenario['instance']->duplicate($scenario['order']);

        $this->assertContains($oldQuote, $scenario['saved']);
    }

    public function testDuplicateCopiesTheAvailablePaymentMethodOfTheOriginalQuote()
    {
        $oldPayment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class)
            ->onlyMethods(['getMethod', 'getAdditionalInformation'])->getMock();
        $oldPayment->method('getMethod')->willReturn('buckaroo_magento2_ideal');
        $oldPayment->method('getAdditionalInformation')->willReturn(['issuer' => 'INGBNL2A']);
        $oldQuote = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub::class)
            ->onlyMethods(['getId', 'getCustomerId', 'setIsActive', 'getPayment'])->getMock();
        $oldQuote->method('getId')->willReturn(123);
        $oldQuote->method('getPayment')->willReturn($oldPayment);

        $scenario = $this->buildDuplicateScenario([
            'oldQuote' => $oldQuote,
            'billing' => $this->createOrderAddress('Jane', 'Utrecht', 'NL'),
        ]);
        $method = $this->getFakeMock(\Magento\Payment\Model\MethodInterface::class)->getMock();
        $method->expects($this->once())->method('isAvailable')->with($scenario['quote'])->willReturn(true);
        $scenario['paymentHelper']->method('getMethodInstance')->with('buckaroo_magento2_ideal')->willReturn($method);
        $newPayment = $scenario['payment'];
        $newPayment->expects($this->once())->method('setMethod')->with('buckaroo_magento2_ideal');
        $newPayment->expects($this->once())->method('setQuote')->with($scenario['quote']);
        $newPayment->expects($this->once())->method('setAdditionalInformation')->with('issuer', 'INGBNL2A');

        $scenario['instance']->duplicate($scenario['order']);
    }

    public function testDuplicateAddsAFreshCopyOfEachOrderedProduct()
    {
        $product = $this->getFakeMock(Product::class)->onlyMethods(['setCustomOptions'])->getMock();
        $customOptions = [];
        $product->method('setCustomOptions')->willReturnCallback(function ($options) use (&$customOptions) {
            $customOptions[] = $options;
        });
        $item = $this->createOrderItem('5', 'Joust Duffle Bag');
        $scenario = $this->buildDuplicateScenario(['items' => [$item], 'storeId' => '1']);
        $scenario['productRepository']->expects($this->once())->method('getById')
            ->with($this->identicalTo(5), false, $this->identicalTo(1))->willReturn($product);
        $added = [];
        $scenario['quote']->method('addProduct')->willReturnCallback(function ($addedProduct) use (&$added) {
            $added[] = $addedProduct;
            return null;
        });
        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $scenario['instance']->duplicate($scenario['order']);

        $this->assertCount(1, $added);
        $this->assertNotSame($product, $added[0], 'The cached product is not changed');
        $this->assertSame([[]], $customOptions);
    }

    public function testDuplicateNamesAnUnavailableProductOnce()
    {
        $scenario = $this->buildDuplicateScenario([
            'items' => [$this->createOrderItem(5, 'Sold Out Bag'), $this->createOrderItem(5, 'Sold Out Bag')],
        ]);
        $scenario['productRepository']->method('getById')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('Not found')));
        $this->messageManager->expects($this->once())->method('addWarningMessage')->with(__(
            'Some products from your order are no longer available and were not added to your cart: %1',
            'Sold Out Bag'
        ));

        $scenario['instance']->duplicate($scenario['order']);
    }

    /**
     * @param int|string $productId
     * @param string     $name
     * @return Item
     */
    private function createOrderItem($productId, string $name)
    {
        $item = $this->getFakeMock(Item::class)->onlyMethods(['getProductId', 'getName', 'getBuyRequest'])->getMock();
        $item->method('getProductId')->willReturn($productId);
        $item->method('getName')->willReturn($name);
        $item->method('getBuyRequest')->willReturn(new \Magento\Framework\DataObject(['qty' => 1]));

        return $item;
    }

    private function createOrderAddress(string $firstname, string $city, string $countryId)
    {
        $address = $this->getFakeMock(Address::class)->getMock();
        $address->method('getFirstname')->willReturn($firstname);
        $address->method('getLastname')->willReturn('Roe');
        $address->method('getCompany')->willReturn('Acme');
        $address->method('getStreet')->willReturn(['Main 1']);
        $address->method('getCity')->willReturn($city);
        $address->method('getRegionId')->willReturn(12);
        $address->method('getRegion')->willReturn('Utrecht');
        $address->method('getPostcode')->willReturn('1234AB');
        $address->method('getCountryId')->willReturn($countryId);
        $address->method('getTelephone')->willReturn('0612');
        $address->method('getFax')->willReturn('0613');

        return $address;
    }

    /**
     * Order, restored quote and collaborators for duplicate(); the original quote is gone unless given
     *
     * @param array $options
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function buildDuplicateScenario(array $options): array
    {
        $options += [
            'orderQuoteId' => 123, 'storeId' => 1, 'customerId' => null, 'items' => [], 'billing' => null,
            'shipping' => null, 'shippingMethod' => null, 'shippingAmount' => null, 'baseShippingAmount' => null,
            'coupon' => null, 'data' => [], 'oldQuote' => null, 'restoredGiftCardsAmount' => null,
        ];

        $order = $this->getFakeMock(Order::class)->getMock();
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getQuoteId')->willReturn($options['orderQuoteId']);
        $order->method('getStoreId')->willReturn($options['storeId']);
        $order->method('getCustomerId')->willReturn($options['customerId']);
        $order->method('getCustomerEmail')->willReturn('jane@example.com');
        $order->method('getAllVisibleItems')->willReturn($options['items']);
        $order->method('getBillingAddress')->willReturn($options['billing']);
        $order->method('getShippingAddress')->willReturn($options['shipping']);
        $order->method('getShippingMethod')->willReturn($options['shippingMethod']);
        $order->method('getShippingAmount')->willReturn($options['shippingAmount']);
        $order->method('getBaseShippingAmount')->willReturn($options['baseShippingAmount']);
        $order->method('getCouponCode')->willReturn($options['coupon']);
        $order->method('getData')->willReturnCallback(fn ($key) => $options['data'][$key] ?? null);

        $billingAddress = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class)
            ->onlyMethods(['importOrderAddress'])->getMock();
        $shippingAddress = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AddressStub::class)
            ->onlyMethods(['importOrderAddress', 'setCollectShippingRates', 'setShippingMethod'])->getMock();
        $payment = $this->getFakeMock(\Magento\Quote\Model\Quote\Payment::class)
            ->onlyMethods(['setMethod', 'setQuote', 'setAdditionalInformation', 'getMethod'])->getMock();

        $quote = $this->getFakeMock(Quote::class)
            ->onlyMethods([
                'getBillingAddress', 'getShippingAddress', 'collectTotals', 'addProduct', 'getPayment', 'getStoreId',
                'getId',
            ])
            ->getMock();
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getId')->willReturn(555);
        if ($options['restoredGiftCardsAmount'] !== null) {
            $quote->method('collectTotals')->willReturnCallback(function () use ($quote, $options) {
                $quote->setData('gift_cards_amount', $options['restoredGiftCardsAmount']);
                return $quote;
            });
        }
        $this->quoteFactory->method('create')->willReturn($quote);

        if ($options['oldQuote']) {
            $this->cartRepository->method('get')->willReturn($options['oldQuote']);
        } else {
            $this->cartRepository->method('get')
                ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('Gone')));
        }
        $saved = new \ArrayObject();
        $this->cartRepository->method('save')->willReturnCallback(function ($cart) use ($saved) {
            $saved[] = $cart;
        });

        $this->storeManager->method('getStore')->willReturn($this->getFakeMock(Store::class)->getMock());

        $cartMerger = $this->getFakeMock(\Buckaroo\Magento2\Service\Sales\Quote\SecondChanceCartMerger::class)
            ->getMock();
        $productRepository = $this->getFakeMock(\Magento\Catalog\Api\ProductRepositoryInterface::class)->getMock();
        $paymentHelper = $this->getFakeMock(\Magento\Payment\Helper\Data::class)->getMock();
        $secondChanceConfig = $this->getFakeMock(\Buckaroo\Magento2\Model\ConfigProvider\SecondChance::class)
            ->getMock();

        $instance = $this->getInstance([
            'cartRepository' => $this->cartRepository,
            'checkoutSession' => $this->checkoutSession,
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
            'storeManager' => $this->storeManager,
            'paymentHelper' => $paymentHelper,
            'productRepository' => $productRepository,
            'secondChanceConfig' => $secondChanceConfig,
            'cartMerger' => $cartMerger,
        ]);

        return [
            'instance' => $instance, 'order' => $order, 'quote' => $quote, 'payment' => $payment,
            'billingAddress' => $billingAddress, 'shippingAddress' => $shippingAddress, 'saved' => $saved,
            'cartMerger' => $cartMerger, 'productRepository' => $productRepository,
            'paymentHelper' => $paymentHelper, 'secondChanceConfig' => $secondChanceConfig,
        ];
    }
}
