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
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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

    /**
     * @var array Filters applied to the customer cart collection, as [field, condition]
     */
    private $cartFilters = [];

    /**
     * @var array Filters applied to the order lookup, as [field, condition]
     */
    private $orderFilters = [];

    /**
     * @var Log|MockObject
     */
    private $logger;

    /**
     * @var array Product repository lookups, as [product ID, edit mode, store ID]
     */
    private $productLookups = [];

    /**
     * @var Product[] Products returned by the product repository
     */
    private $repositoryProducts = [];

    /**
     * @var array Custom options set per product ID
     */
    private $customOptions = [];

    /**
     * @var string|null Reason addProduct() gives when it cannot add the product
     */
    private $addProductError = null;

    protected function setUp(): void
    {
        $this->customerCarts = [];
        $this->existingOrders = [];
        $this->added = [];
        $this->cartFilters = [];
        $this->orderFilters = [];
        $this->productLookups = [];
        $this->repositoryProducts = [];
        $this->customOptions = [];
        $this->addProductError = null;
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->logger = $this->createMock(Log::class);
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
        // Only the customer's active carts of the order's store are looked at
        $this->assertSame(
            [
                ['customer_id', ['eq' => 42]],
                ['store_id', ['eq' => 3]],
                ['is_active', ['eq' => 1]],
            ],
            $this->cartFilters
        );
        $this->assertSame([], $this->orderFilters, 'No reserved ID looks like a restore, so no order lookup');
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
        $this->assertSame([['increment_id', ['in' => ['300000280']]]], $this->orderFilters);
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

    public function testQuantityTheOrderCoversIsCountedOnceAcrossCarts(): void
    {
        // Three ordered: the first cart covers one, the second cart two of its three
        $order = $this->createOrder(42, [$this->createOrderItem(1, 3, [])]);
        $first = $this->createCart(801, 42, null, [$this->createCartItem(1, 1, [])]);
        $second = $this->createCart(802, 42, null, [$this->createCartItem(1, 3, [])]);
        $this->customerCarts = [801 => $first, 802 => $second];

        $previousCarts = $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[1, 1.0]], $this->addedQuantities());
        $this->assertSame([$first, $second], $previousCarts);
    }

    public function testProductTheOrderDoesNotHoldIsKeptFromEveryCart(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(1, 1, [])]);
        $this->customerCarts = [
            $this->createCart(801, 42, null, [$this->createCartItem(2, 1, [])]),
            $this->createCart(802, 42, null, [$this->createCartItem(2, 1, [])]),
        ];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[2, 1.0], [2, 1.0]], $this->addedQuantities());
    }

    public function testOrderLinesOfTheSameProductAndOptionsAreAddedUp(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(1, 1, []), $this->createOrderItem(1, 1, [])]);
        $this->customerCarts = [$this->createCart(801, 42, null, [$this->createCartItem(1, 2, [])])];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([], $this->added);
    }

    public function testSameProductWithOtherOptionsIsASeparateLine(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(62, 1, self::HOODIE_XS_BLACK)]);
        $otherSize = ['super_attribute' => ['93' => '50', '143' => '167']];
        $this->customerCarts = [$this->createCart(801, 42, null, [$this->createCartItem(62, 1, $otherSize)])];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[62, 1.0]], $this->addedQuantities());
        $this->assertSame(['93' => '50', '143' => '167'], $this->added[0][1]['super_attribute']);
    }

    public function testCartAfterARestoredCartIsStillMerged(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $this->customerCarts = [
            $this->createCart(765, 42, '300000280-1', [$this->createCartItem(1, 1, [])]),
            $this->createCart(766, 42, null, [$this->createCartItem(2, 1, [])]),
        ];
        $this->existingOrders = ['300000280'];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[2, 1.0]], $this->addedQuantities());
    }

    public function testEachRestoredOrderIdIsLookedUpOnce(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $this->customerCarts = [
            $this->createCart(765, 42, '300000280-1', []),
            $this->createCart(766, 42, '300000280-2', []),
        ];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([['increment_id', ['in' => ['300000280']]]], $this->orderFilters);
    }

    public function testReservedIdWithTextAfterTheSuffixIsNotARestore(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $this->customerCarts = [$this->createCart(765, 42, '300000280-1-b', [$this->createCartItem(1, 1, [])])];
        $this->existingOrders = ['300000280'];

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[1, 1.0]], $this->addedQuantities());
        $this->assertSame([], $this->orderFilters);
    }

    public function testCollectionEntriesThatAreNotCartsAreIgnored(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $cart = $this->createCart(801, 42, null, []);
        $this->customerCarts = [800 => new DataObject(['id' => 800]), 801 => $cart];

        $previousCarts = $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([$cart], $previousCarts);
    }

    public function testAddedProductIsAFreshCopyWithoutCustomOptions(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $this->customerCarts = [$this->createCart(801, 42, null, [$this->createCartItem(2, 1, [])])];
        $this->logger->expects($this->never())->method('addError');

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);

        $this->assertSame([[2, false, 3]], $this->productLookups);
        $this->assertSame([2 => []], $this->customOptions);
        $this->assertCount(1, $this->added);
        $this->assertNotSame($this->repositoryProducts[0], $this->added[0][2], 'The cached product is not changed');
    }

    public function testProductThatCannotBeAddedIsLogged(): void
    {
        $order = $this->createOrder(42, [$this->createOrderItem(9, 1, [])]);
        $this->customerCarts = [$this->createCart(801, 42, null, [$this->createCartItem(2, 1, [])])];
        $this->addProductError = 'Out of stock';
        $this->logger->expects($this->once())->method('addError')
            ->with('Second Chance: could not keep a cart product: Out of stock');

        $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0);
    }

    public function testGuestOrderWithoutSessionCartIdDoesNotLoadACart(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $order->expects($this->never())->method('getAllVisibleItems');
        $this->cartRepository->expects($this->never())->method('get');

        $this->assertSame([], $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 0));
    }

    public function testGuestOrderIgnoresAnInactiveSessionCart(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $this->cartRepository->method('get')
            ->willReturn($this->createCart(768, null, null, [$this->createCartItem(2, 1, [])], false));

        $this->assertSame([], $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 768));
        $this->assertSame([], $this->added);
    }

    public function testGuestCartOfTheOrderStoreGivenAsStringIsUsed(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $guestCart = $this->createCart(768, null, null, [], true, '3');
        $this->cartRepository->method('get')->willReturn($guestCart);

        $this->assertSame([$guestCart], $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 768));
    }

    public function testGuestCartOfAnotherStoreIsLeftAlone(): void
    {
        $order = $this->createOrder(null, [$this->createOrderItem(1, 1, [])]);
        $this->cartRepository->method('get')->willReturn($this->createCart(768, null, null, [], true, 4));

        $this->assertSame([], $this->createMerger()->mergeInto($this->createRestoredQuote(), $order, 768));
    }

    private function createMerger(): SecondChanceCartMerger
    {
        $quoteCollection = $this->createMock(QuoteCollection::class);
        $quoteCollection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use ($quoteCollection) {
                $this->cartFilters[] = [$field, $condition];
                return $quoteCollection;
            }
        );
        $quoteCollection->method('getItems')->willReturnCallback(function () {
            return $this->customerCarts;
        });
        $quoteCollectionFactory = $this->createMock(QuoteCollectionFactory::class);
        $quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $requested = [];
        $orderCollection = $this->createMock(OrderCollection::class);
        $orderCollection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$requested, $orderCollection) {
                $this->orderFilters[] = [$field, $condition];
                $requested = $condition['in'];
                return $orderCollection;
            }
        );
        $orderCollection->method('getColumnValues')->with('increment_id')->willReturnCallback(
            function () use (&$requested) {
                return array_values(array_intersect($requested, $this->existingOrders));
            }
        );
        $orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturn($orderCollection);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getById')->willReturnCallback(function ($productId, $editMode, $storeId) {
            $this->productLookups[] = [$productId, $editMode, $storeId];
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($productId);
            $product->method('setCustomOptions')->willReturnCallback(function ($options) use ($productId, $product) {
                $this->customOptions[$productId] = $options;
                return $product;
            });
            $this->repositoryProducts[] = $product;
            return $product;
        });

        return new SecondChanceCartMerger(
            $quoteCollectionFactory,
            $orderCollectionFactory,
            $this->cartRepository,
            $productRepository,
            $this->logger
        );
    }

    /**
     * @return Quote|MockObject
     */
    private function createRestoredQuote()
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('addProduct')->willReturnCallback(function ($product, DataObject $buyRequest) {
            $this->added[] = [(int) $product->getId(), $buyRequest->getData(), $product];
            return $this->addProductError;
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
        // Order data loaded from the database holds its IDs as strings
        $order->method('getCustomerId')->willReturn($customerId === null ? null : (string) $customerId);
        $order->method('getStoreId')->willReturn('3');
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

    /**
     * @param int             $id
     * @param int|null        $customerId
     * @param string|null     $reservedOrderId
     * @param array           $items
     * @param bool            $isActive
     * @param int|string      $storeId
     * @return QuoteStub|MockObject
     */
    private function createCart(
        int $id,
        ?int $customerId,
        ?string $reservedOrderId,
        array $items,
        bool $isActive = true,
        $storeId = 3
    ) {
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
        $cart->method('getIsActive')->willReturn($isActive);
        $cart->method('getStoreId')->willReturn($storeId);
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
