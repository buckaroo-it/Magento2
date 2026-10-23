<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Quote;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\Sales\Quote\SecondChanceCartMerger;
use Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * BTI-1913 / BTI-1914
 */
class SecondChanceCartMergerTest extends TestCase
{
    private const HOODIE_XS_BLACK = ['super_attribute' => ['93' => '49', '143' => '167']];

    /**
     * @var Quote[]
     */
    private $customerCarts = [];

    /**
     * @var string[] Increment IDs of the orders that exist
     */
    private $existingOrders = [];

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var array Products added to the restored cart, as [product ID, buy request data]
     */
    private $added = [];

    protected function setUp(): void
    {
        $this->customerCarts = [];
        $this->existingOrders = [];
        $this->added = [];
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
    }

    public function testKeepsWhatTheCustomerAddedWithoutDoublingTheOrder(): void
    {
        $order = $this->createOrder(42, [
            $this->createOrderItem(1, 1, []),
            $this->createOrderItem(62, 2, self::HOODIE_XS_BLACK),
        ]);
        // The failed order's cart, restored after the failed payment, plus what the customer added since
        $failedCart = $this->createCart(760, 42, null, [
            $this->createCartItem(1, 3, []),
            $this->createCartItem(62, 2, ['super_attribute' => ['143' => 167, '93' => 49]]),
            $this->createCartItem(2, 1, []),
        ]);
        $this->customerCarts = [$failedCart];

        $restored = $this->createRestoredQuote();
        $previousCarts = $this->createMerger()->mergeInto($restored, $order, 0);

        $this->assertSame([[1, 2.0], [2, 1.0]], $this->addedQuantities());
        $this->assertSame([$failedCart], $previousCarts);
    }

    public function testDoesNotMergeACartRestoredForAnotherOrder(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(2, 1, [])]);
        $otherRestore = $this->createCart(765, 42, '300000280-1', [$this->createCartItem(1, 1, [])]);
        $this->customerCarts = [$otherRestore];
        $this->existingOrders = ['300000280'];

        $previousCarts = $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([], $this->added);
        $this->assertSame([$otherRestore], $previousCarts, 'It is still replaced, so one cart stays active');
    }

    public function testMergesACartWhoseReservedIdOnlyLooksLikeARestore(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(2, 1, [])]);
        // Increment ID format "NL-<number>": there is no order "NL"
        $cart = $this->createCart(770, 42, 'NL-100001', [$this->createCartItem(1, 1, [])]);
        $this->customerCarts = [$cart];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[1, 1.0]], $this->addedQuantities());
    }

    public function testGuestOrderUsesTheGuestCartInTheSession(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $guestCart = $this->createCart(768, null, null, [$this->createCartItem(2, 1, [])]);
        $this->cartRepository->method('get')->with(768)->willReturn($guestCart);

        $previousCarts = $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 768);

        $this->assertSame([[2, 1.0]], $this->addedQuantities());
        $this->assertSame([$guestCart], $previousCarts);
    }

    public function testGuestOrderLeavesASignedInAccountCartAlone(): void
    {
        // BTI-1758: the guest signed in afterwards, so the session cart is now their account cart
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $accountCart = $this->createCart(588, 26, null, [$this->createCartItem(2, 1, [])]);
        $this->cartRepository->method('get')->with(588)->willReturn($accountCart);

        $previousCarts = $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 588);

        $this->assertSame([], $this->added);
        $this->assertSame([], $previousCarts);
    }

    public function testGuestOrderWithoutSessionCart(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $this->cartRepository->method('get')->willThrowException(new NoSuchEntityException(__('Gone')));

        $this->assertSame([], $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 5));
    }

    public function testDeactivate(): void
    {
        $cart = $this->createCart(760, 42, null, []);
        $cart->expects($this->once())->method('setIsActive')->with(false)->willReturnSelf();
        $this->cartRepository->expects($this->once())->method('save')->with($cart);

        $this->createMerger()->deactivate([$cart]);
    }

    private function createMerger(): SecondChanceCartMerger
    {
        $quoteCollection = $this->createMock(QuoteCollection::class);
        $quoteCollection->method('addFieldToFilter')->willReturnSelf();
        $quoteCollection->method('getItems')->willReturnCallback(function () {
            return $this->customerCarts;
        });
        $quoteCollectionFactory = $this->createMock(QuoteCollectionFactory::class);
        $quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $requested = [];
        $orderCollection = $this->createMock(OrderCollection::class);
        $orderCollection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$requested, $orderCollection) {
                $requested = $condition['in'];
                return $orderCollection;
            }
        );
        $orderCollection->method('getColumnValues')->willReturnCallback(function () use (&$requested) {
            return array_values(array_intersect($requested, $this->existingOrders));
        });
        $orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturn($orderCollection);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturnCallback(function ($productId) {
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($productId);
            return $product;
        });

        return new SecondChanceCartMerger(
            $quoteCollectionFactory,
            $orderCollectionFactory,
            $this->cartRepository,
            $productRepository,
            $this->createMock(Log::class)
        );
    }

    /**
     * @return Quote|MockObject
     */
    private function createRestoredQuote()
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('addProduct')->willReturnCallback(function ($product, DataObject $buyRequest) {
            $this->added[] = [(int) $product->getId(), $buyRequest->getData()];
            return null;
        });

        return $quote;
    }

    private function addedQuantities(): array
    {
        return array_map(function ($added) {
            return [$added[0], (float) $added[1]['qty']];
        }, $this->added);
    }

    private function createOrder(?int $customerId, array $items)
    {
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getAllVisibleItems')->willReturn($items);

        return $order;
    }

    private function createOrderItem(int $productId, float $qty, array $options)
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getQtyOrdered')->willReturn($qty);
        $item->method('getBuyRequest')->willReturn(new DataObject($options + ['qty' => $qty]));

        return $item;
    }

    private function createCart(int $id, ?int $customerId, ?string $reservedOrderId, array $items)
    {
        $cart = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getCustomerId', 'getReservedOrderId', 'getIsActive', 'getStoreId',
                'getAllVisibleItems', 'setIsActive',
            ])
            ->getMock();
        $cart->method('getId')->willReturn($id);
        $cart->method('getCustomerId')->willReturn($customerId);
        $cart->method('getReservedOrderId')->willReturn($reservedOrderId);
        $cart->method('getIsActive')->willReturn(true);
        $cart->method('getStoreId')->willReturn(1);
        $cart->method('getAllVisibleItems')->willReturn($items);

        return $cart;
    }

    private function createCartItem(int $productId, float $qty, array $options)
    {
        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQty', 'getBuyRequest'])
            ->getMock();
        $item->setData('product_id', $productId);
        $item->method('getQty')->willReturn($qty);
        $item->method('getBuyRequest')->willReturn(new DataObject($options + ['qty' => $qty, 'uenc' => 'x']));

        return $item;
    }
}
