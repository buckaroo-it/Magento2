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
        $this->checkoutSession = $this->getFakeMock(CheckoutSession::class)->getMock();
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
        
        $this->quoteFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);
            
        $oldQuote->expects($this->once())
            ->method('load')
            ->with($quoteId)
            ->willReturnSelf();
            
        $oldQuote->expects($this->atLeastOnce())->method('getId')->willReturn($quoteId);
        $oldQuote->expects($this->once())->method('getStoreId')->willReturn($storeId);
        
        $this->storeManager->expects($this->once())
            ->method('getStore')
            ->with($storeId)
            ->willReturn($store);
            
        $newQuote->expects($this->once())->method('merge')->with($oldQuote);
        $newQuote->expects($this->exactly(2))->method('save');
        $newQuote->expects($this->once())->method('setStore')->with($store);
        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');
        $newQuote->expects($this->once())->method('setTriggerRecollect')->with('1');
        $newQuote->expects($this->once())->method('setReservedOrderId')->with(null);
        
        $this->checkoutSession->expects($this->once())->method('replaceQuote')->with($newQuote);
        $this->checkoutSession->expects($this->once())->method('unsLastRealOrderId');
        $this->checkoutSession->expects($this->once())->method('unsLastOrderId');
        $this->checkoutSession->expects($this->once())->method('unsLastSuccessQuoteId');
        $this->checkoutSession->expects($this->once())->method('unsRedirectUrl');
        $this->checkoutSession->expects($this->once())->method('unsLastQuoteId');
        
        $this->cartRepository->expects($this->once())->method('save')->with($newQuote);

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
        
        $this->quoteFactory->expects($this->once())
            ->method('create')
            ->willReturn($oldQuote);
            
        $oldQuote->expects($this->once())
            ->method('load')
            ->with($quoteId)
            ->willReturnSelf();
            
        $oldQuote->expects($this->once())->method('getId')->willReturn(null);

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('recreateById', [$quoteId], $instance);
        $this->assertNull($result);
    }

    public function testDuplicate()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        $newQuote = $this->getFakeMock(Quote::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        $billingAddress = $this->getFakeMock(Address::class)->getMock();
        $shippingAddress = $this->getFakeMock(Address::class)->getMock();
        $quoteBillingAddress = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)->getMock();
        $quoteShippingAddress = $this->getFakeMock(\Magento\Quote\Model\Quote\Address::class)->getMock();
        
        // Setup order data
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
        $order->expects($this->once())->method('getQuoteId')->willReturn(123);
        $order->expects($this->atLeastOnce())->method('getStore')->willReturn($store);
        $order->expects($this->once())->method('getCustomerId')->willReturn(1);
        $order->expects($this->once())->method('getCustomerEmail')->willReturn('test@example.com');
        $order->expects($this->once())->method('getCustomerFirstname')->willReturn('John');
        $order->expects($this->once())->method('getCustomerLastname')->willReturn('Doe');
        $order->expects($this->once())->method('getBillingAddress')->willReturn($billingAddress);
        $order->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddress);
        $order->expects($this->once())->method('getShippingMethod')->willReturn('flatrate_flatrate');
        
        // Setup order items
        $orderItem = $this->getFakeMock(Item::class)->getMock();
        $orderItem->expects($this->once())->method('getProductId')->willReturn(1);
        $orderItem->expects($this->once())->method('getQtyOrdered')->willReturn(2);
        $orderItem->expects($this->once())->method('getProductOptionByCode')->with('info_buyRequest')->willReturn(['qty' => 2]);
        
        $order->expects($this->once())->method('getAllVisibleItems')->willReturn([$orderItem]);
        
        // Setup quotes
        $this->quoteFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);
            
        $oldQuote->expects($this->once())->method('load')->with(123)->willReturnSelf();
        $oldQuote->expects($this->once())->method('getId')->willReturn(123);
        
        // Setup new quote
        $newQuote->expects($this->once())->method('setStore')->with($store);
        $newQuote->expects($this->once())->method('setCustomerId')->with(1);
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('test@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('John');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('Doe');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(false);
        $newQuote->expects($this->once())->method('getBillingAddress')->willReturn($quoteBillingAddress);
        $newQuote->expects($this->once())->method('getShippingAddress')->willReturn($quoteShippingAddress);
        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');
        $newQuote->expects($this->once())->method('save');
        
        // Setup product
        $product = $this->getFakeMock(Product::class)->getMock();
        $product->expects($this->once())->method('getId')->willReturn(1);
        
        $this->productFactory->expects($this->once())
            ->method('create')
            ->willReturn($product);
            
        $product->expects($this->once())->method('load')->with(1)->willReturnSelf();
        
        $newQuote->expects($this->once())
            ->method('addProduct')
            ->with($product, $this->isInstanceOf(\Magento\Framework\DataObject::class));
        
        // Setup addresses
        $quoteBillingAddress->expects($this->once())->method('importOrderAddress')->with($billingAddress);
        $quoteShippingAddress->expects($this->once())->method('importOrderAddress')->with($shippingAddress);
        $quoteShippingAddress->expects($this->once())->method('setShippingMethod')->with('flatrate_flatrate');
        $quoteShippingAddress->expects($this->once())->method('setCollectShippingRates')->with(true);
        
        // Setup checkout session
        $this->checkoutSession->expects($this->once())->method('replaceQuote')->with($newQuote);
        $this->checkoutSession->expects($this->once())->method('setQuoteId');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'productFactory' => $this->productFactory,
            'checkoutSession' => $this->checkoutSession,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertEquals($newQuote, $result);
    }

    public function testDuplicateWithGuestCustomer()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        $newQuote = $this->getFakeMock(Quote::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        
        // Setup guest order
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
        $order->expects($this->once())->method('getQuoteId')->willReturn(123);
        $order->expects($this->atLeastOnce())->method('getStore')->willReturn($store);
        $order->expects($this->once())->method('getCustomerId')->willReturn(null); // Guest customer
        $order->expects($this->once())->method('getCustomerEmail')->willReturn('guest@example.com');
        $order->expects($this->once())->method('getCustomerFirstname')->willReturn('Guest');
        $order->expects($this->once())->method('getCustomerLastname')->willReturn('User');
        $order->expects($this->once())->method('getAllVisibleItems')->willReturn([]);
        $order->expects($this->once())->method('getBillingAddress')->willReturn(null);
        $order->expects($this->once())->method('getShippingAddress')->willReturn(null);
        
        $this->quoteFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($oldQuote, $newQuote);
            
        $oldQuote->expects($this->once())->method('load')->with(123)->willReturnSelf();
        $oldQuote->expects($this->once())->method('getId')->willReturn(123);
        
        // Setup guest quote
        $newQuote->expects($this->once())->method('setStore')->with($store);
        $newQuote->expects($this->once())->method('setCustomerEmail')->with('guest@example.com');
        $newQuote->expects($this->once())->method('setCustomerFirstname')->with('Guest');
        $newQuote->expects($this->once())->method('setCustomerLastname')->with('User');
        $newQuote->expects($this->once())->method('setCustomerIsGuest')->with(true);
        $newQuote->expects($this->once())->method('setIsActive')->with(true);
        $newQuote->expects($this->once())->method('collectTotals');
        $newQuote->expects($this->once())->method('save');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'productFactory' => $this->productFactory,
            'checkoutSession' => $this->checkoutSession,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertEquals($newQuote, $result);
    }

    public function testDuplicateWithMissingOriginalQuote()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $oldQuote = $this->getFakeMock(Quote::class)->getMock();
        
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
        $order->expects($this->once())->method('getQuoteId')->willReturn(999);
        
        $this->quoteFactory->expects($this->once())
            ->method('create')
            ->willReturn($oldQuote);
            
        $oldQuote->expects($this->once())->method('load')->with(999)->willReturnSelf();
        $oldQuote->expects($this->once())->method('getId')->willReturn(null);
        
        $this->logger->expects($this->once())
            ->method('addError')
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
        
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
        $order->expects($this->once())->method('getQuoteId')->willReturn(123);
        
        $this->quoteFactory->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Test exception'));
        
        $this->logger->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Error duplicating order to quote'));
            
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage');

        $instance = $this->getInstance([
            'quoteFactory' => $this->quoteFactory,
            'messageManager' => $this->messageManager,
            'logger' => $this->logger,
        ]);

        $result = $this->invokeArgs('duplicate', [$order], $instance);
        $this->assertNull($result);
    }
}
