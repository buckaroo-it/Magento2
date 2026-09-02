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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A payment provider validates the article lines of a partial capture against the
 * ones it saw during the reserve and uses the RESERVED unit price. A lump discount line can
 * therefore never be captured in part: Klarna answered a €19.36 capture that carried a −€16.00
 * slice of a −€32.00 reserved line with
 * "Sum of given articles (3,36) is not equal to the given amount (19,36)" — it had used the
 * reserved −32.00.
 *
 * The discount now rides on the item line itself, so any subset of lines, in any quantity,
 * still adds up at reserved prices.
 */
class DiscountOnItemLinesTest extends \Buckaroo\Magento2\Test\BaseTest
{
    /**
     * Bundle price calculation, as an order item records it in product_options.
     */
    private const CALCULATE_CHILD = 0;
    private const CALCULATE_PARENT = 1;

    protected $instanceClass = 'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\BillinkHandler';

    /**
     * @param float $discountAmount
     * @param float $compensation
     * @param float $qty
     * @param bool  $pricesIncludeTax
     * @param float $expected
     */
    #[DataProvider('unitDiscountProvider')]
    public function testTheUnitDiscountIsThePerUnitShareOfTheItemDiscount(
        float $discountAmount,
        float $compensation,
        float $qty,
        bool $pricesIncludeTax,
        float $expected
    ): void {
        $instance = $this->buildInstance($pricesIncludeTax);

        $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $item->method('getDiscountAmount')->willReturn($discountAmount);
        $item->method('getDiscountTaxCompensationAmount')->willReturn($compensation);

        $this->assertEqualsWithDelta(
            $expected,
            $this->invokeArgs('getUnitDiscount', [$item, $qty], $instance),
            0.0001
        );
    }

    public static function unitDiscountProvider(): array
    {
        return [
            'single unit' => [16.00, 0.0, 1.0, true, 16.00],
            'split over the units' => [16.99, 0.0, 3.0, true, 5.663333],
            'compensation counts when catalog prices exclude tax' => [5.00, 1.05, 1.0, false, 6.05],
            'compensation ignored when catalog prices include tax' => [5.00, 1.05, 1.0, true, 5.00],
            'no discount' => [0.0, 0.0, 1.0, true, 0.0],
            'a sub-cent discount is ignored' => [0.005, 0.0, 1.0, true, 0.0],
            'a zero quantity cannot be divided' => [16.00, 0.0, 0.0, true, 0.0],
        ];
    }

    /**
     * The capture must repeat the price the reserve sent for the same unit, so both sides derive
     * the unit discount from the same figures.
     */
    public function testTheCaptureRepeatsTheReservedUnitPrice(): void
    {
        $instance = $this->buildInstance(true);

        // Reserve works off the quote item, capture off the order item behind the invoice item.
        $quoteItem = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteItemStub::class)->getMock();
        $quoteItem->method('getDiscountAmount')->willReturn(16.99);
        $quoteItem->method('getDiscountTaxCompensationAmount')->willReturn(0.0);

        $orderItem = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $orderItem->method('getDiscountAmount')->willReturn(16.99);
        $orderItem->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $orderItem->method('getQtyOrdered')->willReturn(3.0);

        $reserved = $this->invokeArgs('getUnitDiscount', [$quoteItem, 3.0], $instance);
        $captured = $this->invokeArgs('getReservedUnitDiscount', [$orderItem], $instance);

        $this->assertSame($reserved, $captured);
    }

    public function testAnInvoiceItemWithoutAnOrderItemCarriesNoDiscount(): void
    {
        $instance = $this->buildInstance(true);

        $this->assertSame(0.0, $this->invokeArgs('getReservedUnitDiscount', [null], $instance));
    }

    /**
     * The product line is the gross unit price minus that unit's discount, so the line alone is
     * the item's whole contribution to the order total.
     */
    public function testTheProductPriceCarriesTheDiscount(): void
    {
        $instance = $this->buildInstance(true);

        $item = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\InvoiceItemStub::class)
            ->onlyMethods(['getPriceInclTax', 'getDiscountAmount', 'getWeeeTaxAppliedAmount'])
            ->getMock();
        $item->method('getPriceInclTax')->willReturn(35.36);
        $item->method('getDiscountAmount')->willReturn(16.00);
        $item->method('getWeeeTaxAppliedAmount')->willReturn(0.0);

        $this->assertEqualsWithDelta(
            19.36,
            $this->invokeArgs('getDiscountedProductPrice', [$item, 16.00], $instance),
            0.001
        );
    }

    /**
     * The shipping discount rides on the shipping line for the same reason, and Magento settles
     * both on the first invoice.
     *
     * @param float $shippingDiscount
     * @param float $compensation
     * @param bool  $shippingIncludesTax
     * @param float $expected
     */
    #[DataProvider('shippingDiscountProvider')]
    public function testTheShippingDiscountRidesOnTheShippingLine(
        float $shippingDiscount,
        float $compensation,
        bool $shippingIncludesTax,
        float $expected
    ): void {
        $instance = $this->buildInstance($shippingIncludesTax);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getShippingDiscountAmount')->willReturn($shippingDiscount);
        $order->method('getShippingDiscountTaxCompensationAmount')->willReturn($compensation);

        $this->assertEqualsWithDelta(
            $expected,
            $this->invokeArgs('getShippingDiscount', [$order], $instance),
            0.001
        );
    }

    /**
     * shipping_incl_tax is the gross cost BEFORE the discount while shipping_discount_amount is
     * net of tax, so subtracting one from the other leaves the tax on the discount behind and the
     * remainder ends up on a fabricated article the provider refuses.
     *
     * @param float $shippingAmount
     * @param float $shippingInclTax
     * @param float $shippingTaxAmount
     * @param float $shippingDiscount
     * @param float $expected
     */
    #[DataProvider('discountedShippingProvider')]
    public function testTheShippingLineIsGrossOfTaxAndNetOfItsDiscount(
        float $shippingAmount,
        float $shippingInclTax,
        float $shippingTaxAmount,
        float $shippingDiscount,
        float $expected
    ): void {
        $instance = $this->buildInstance(false);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getShippingAmount')->willReturn($shippingAmount);
        $order->method('getShippingInclTax')->willReturn($shippingInclTax);
        $order->method('getShippingTaxAmount')->willReturn($shippingTaxAmount);
        $order->method('getShippingDiscountAmount')->willReturn(-$shippingDiscount);
        $order->method('getShippingDiscountTaxCompensationAmount')->willReturn(0.0);

        $this->assertEqualsWithDelta(
            $expected,
            $this->invokeArgs('getDiscountedShippingAmount', [$order], $instance),
            0.001
        );
    }

    public static function discountedShippingProvider(): array
    {
        return [
            // Magento discounted the cost before taxing it, so shipping_tax_amount is already
            // the tax on the 8.00 that is actually charged.
            'discount applied before tax' => [10.00, 12.10, 1.68, 2.00, 9.68],
            'no discount leaves the gross cost alone' => [10.00, 12.10, 2.10, 0.0, 12.10],
            'a discount covering the whole cost leaves no line' => [10.00, 12.10, 0.0, 10.00, 0.0],
            'free shipping has no line either' => [0.0, 0.0, 0.0, 0.0, 0.0],
        ];
    }

    public static function shippingDiscountProvider(): array
    {
        return [
            'discount incl. tax' => [-2.42, 0.0, true, 2.42],
            'discount plus compensation when shipping prices exclude tax' => [-2.00, 0.42, false, 2.42],
            'compensation ignored when shipping prices include tax' => [-2.42, 0.42, true, 2.42],
            'no shipping discount' => [0.0, 0.0, true, 0.0],
        ];
    }

    /**
     * Only what could not be put on an item or the shipping line is left for a global line -
     * and a global line is indivisible, so it is settled on the first invoice alone.
     *
     * @param float $orderDiscount
     * @param array $itemDiscounts
     * @param float $shippingDiscount
     * @param float $expected
     */
    #[DataProvider('globalDiscountProvider')]
    public function testTheGlobalLineOnlyCarriesWhatNoLineCouldAbsorb(
        float $orderDiscount,
        array $itemDiscounts,
        float $shippingDiscount,
        float $expected
    ): void {
        $instance = $this->buildInstance(true);

        $items = [];
        foreach ($itemDiscounts as $discount) {
            $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
            $item->method('getDiscountAmount')->willReturn($discount);
            $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
            $items[] = $item;
        }

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getDiscountAmount')->willReturn($orderDiscount);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $order->method('getShippingDiscountAmount')->willReturn($shippingDiscount);
        $order->method('getAllItems')->willReturn($items);

        $this->setProperty('order', $order, $instance);

        $this->assertEqualsWithDelta(
            $expected,
            (float)$this->invoke('getDiscountAmount', $instance),
            0.001
        );
    }

    public static function globalDiscountProvider(): array
    {
        return [
            'a fully allocated discount leaves nothing behind' => [-32.00, [16.00, 16.00], 0.0, 0.0],
            'the shipping discount is carried by the shipping line' => [-7.50, [5.00], -2.50, 0.0],
            'only an unallocated remainder is left' => [-16.99, [0.0, 0.0], 0.0, -16.99],
            'a partly allocated discount leaves the rest' => [-16.99, [8.00, 0.0], 0.0, -8.99],
            'no discount at all' => [0.0, [0.0], 0.0, 0.0],
        ];
    }

    /**
     * A dynamic-price bundle keeps its prices and its discount on the child items, which
     * getAllVisibleItems() hides. Reading the allocation from the visible items alone sees a parent
     * with discount 0 and reports the children's discount as unallocated, so it is sent a second
     * time as a global discount line on top of child prices already discounted.
     */
    public function testADynamicBundleAllocatesItsDiscountOnTheChildren(): void
    {
        $instance = $this->buildInstance(true);

        $items = [$this->makeBundleParent(615, self::CALCULATE_CHILD)];
        foreach ([23.00, 5.00, 14.00, 19.00] as $index => $discount) {
            $items[] = $this->makeChildItem($discount, 616 + $index, 615);
        }
        $items[] = $this->makeSimpleItem(16.00, 620);

        $this->setProperty('order', $this->makeDiscountedOrder(-77.00, $items), $instance);

        $this->assertEqualsWithDelta(
            0.0,
            (float)$this->invoke('getDiscountAmount', $instance),
            0.001,
            'The bundle children carry the discount, so nothing is left for a global line'
        );
    }

    /**
     * A fixed-price bundle carries its price and its discount on the parent and leaves its
     * children empty, so only the parent may be counted - counting both would report the
     * discount as over-allocated and drop a global line that is genuinely owed.
     */
    public function testAFixedPriceBundleAllocatesItsDiscountOnTheParent(): void
    {
        $instance = $this->buildInstance(true);

        $parent = $this->makeBundleParent(700, self::CALCULATE_PARENT, 30.00);
        $items = [$parent, $this->makeChildItem(0.0, 701, 700), $this->makeChildItem(0.0, 702, 700)];

        $this->setProperty('order', $this->makeDiscountedOrder(-30.00, $items), $instance);

        $this->assertEqualsWithDelta(
            0.0,
            (float)$this->invoke('getDiscountAmount', $instance),
            0.001,
            'The parent carries the whole discount of a fixed-price bundle'
        );
    }

    /**
     * A configurable keeps its price and discount on the parent too, and its child is a label
     * with no money on it.
     */
    public function testAConfigurableChildIsNotCounted(): void
    {
        $instance = $this->buildInstance(true);

        $parent = $this->makeSimpleItem(16.00, 800, 'configurable');
        $items = [$parent, $this->makeChildItem(16.00, 801, 800)];

        $this->setProperty('order', $this->makeDiscountedOrder(-16.00, $items), $instance);

        $this->assertEqualsWithDelta(
            0.0,
            (float)$this->invoke('getDiscountAmount', $instance),
            0.001,
            'The configurable child must not be counted a second time'
        );
    }

    /**
     * The VAT grouping reads the same items. A dynamic bundle parent has no tax rate at all, so
     * grouping by the visible items put the children's whole row total at 0% VAT and split the
     * discount lines over the wrong rates.
     */
    public function testTheVatGroupingReadsTheBundleChildren(): void
    {
        $instance = $this->buildInstance(true);

        $parent = $this->makeBundleParent(615, self::CALCULATE_CHILD);
        $parent->method('getRowTotal')->willReturn(122.00);
        $parent->method('getTaxPercent')->willReturn(null);

        $items = [$parent];
        foreach ([[46.00, 21.0, 616], [10.00, 21.0, 617], [28.00, 0.0, 618], [38.00, 21.0, 619]] as $row) {
            [$rowTotal, $vat, $itemId] = $row;
            $child = $this->makeChildItem(0.0, $itemId, 615);
            $child->method('getRowTotal')->willReturn($rowTotal);
            $child->method('getTaxPercent')->willReturn($vat);
            $items[] = $child;
        }

        $this->setProperty('order', $this->makeDiscountedOrder(0.0, $items), $instance);

        $groups = $this->invoke('getOrderVatGroups', $instance);

        $this->assertEqualsWithDelta(
            94.00,
            $groups[21]['rowTotal'] ?? 0.0,
            0.001,
            'The 21% children are grouped at their own rate'
        );
        $this->assertEqualsWithDelta(
            28.00,
            $groups[0]['rowTotal'] ?? 0.0,
            0.001,
            'The 0% child keeps its own rate'
        );
        $this->assertEqualsWithDelta(
            122.00,
            array_sum(array_column($groups, 'rowTotal')),
            0.001,
            'The children add up to what the parent holds, and the parent is not counted twice'
        );
    }

    /**
     * An amount already settled outside the gateway - reward points, a gift card, store credit -
     * is sent as its own negative line so the articles still sum to what the gateway is asked for.
     *
     * @param string $dataKey
     * @param int    $identifier
     */
    #[DataProvider('settledOutsideTheGatewayProvider')]
    public function testAnAmountSettledOutsideTheGatewayGetsItsOwnLine(string $dataKey, int $identifier): void
    {
        $instance = $this->buildInstance(true);
        $this->setProperty('order', $this->makeOrderWithData([$dataKey => 21.50]), $instance);

        $lines = $this->invoke('getAdditionalLines', $instance)['articles'];

        $this->assertCount(1, $lines);
        $this->assertEquals($identifier, $lines[0]['identifier']);
        $this->assertEqualsWithDelta(-21.50, (float)$lines[0]['price'], 0.001);
    }

    public static function settledOutsideTheGatewayProvider(): array
    {
        return [
            'reward points' => ['reward_currency_amount', 5],
            'gift card' => ['gift_cards_amount', 6],
        ];
    }

    /**
     * Reward points, gift cards and store credit are all Adobe Commerce features read from order
     * data keys rather than from Magento\Reward or Magento\CustomerBalance classes, so the same
     * handler runs unchanged on Open Source - where the keys are simply absent.
     */
    public function testNothingIsSentWhenNoneOfThoseKeysArePresent(): void
    {
        $instance = $this->buildInstance(true);
        $this->setProperty('order', $this->makeOrderWithData([]), $instance);

        $this->assertSame(
            [],
            $this->invoke('getAdditionalLines', $instance),
            'On Open Source these order data keys do not exist, so no line may be sent'
        );
    }

    /**
     * Both can appear on one order, and no two lines may share an article number.
     */
    public function testBothCanAppearTogetherWithDistinctArticleNumbers(): void
    {
        $instance = $this->buildInstance(true);
        $this->setProperty('order', $this->makeOrderWithData([
            'reward_currency_amount' => 5.00,
            'gift_cards_amount' => 10.00,
        ]), $instance);

        $lines = $this->invoke('getAdditionalLines', $instance)['articles'];
        $identifiers = array_column($lines, 'identifier');

        $this->assertCount(2, $lines);
        $this->assertSame($identifiers, array_unique($identifiers), 'Every line needs its own number');
        $this->assertEqualsWithDelta(
            -15.00,
            array_sum(array_map(static fn ($l) => (float)$l['price'], $lines)),
            0.001
        );
    }

    /**
     * @param array $data
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrderWithData(array $data)
    {
        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getData')->willReturnCallback(
            static function ($key = null) use ($data) {
                return $data[$key] ?? null;
            }
        );

        return $order;
    }

    /**
     * @param float $orderDiscount
     * @param array $items
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeDiscountedOrder(float $orderDiscount, array $items)
    {
        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getDiscountAmount')->willReturn($orderDiscount);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $order->method('getShippingDiscountAmount')->willReturn(0.0);
        $order->method('getAllItems')->willReturn($items);

        return $order;
    }

    /**
     * @param int   $itemId
     * @param int   $priceCalculation
     * @param float $discount
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeBundleParent(int $itemId, int $priceCalculation, float $discount = 0.0)
    {
        $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getProductType')->willReturn('bundle');
        $item->method('getProductOptions')->willReturn(['product_calculations' => $priceCalculation]);
        $item->method('getDiscountAmount')->willReturn($discount);
        $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);

        return $item;
    }

    /**
     * @param float $discount
     * @param int   $itemId
     * @param int   $parentItemId
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeChildItem(float $discount, int $itemId, int $parentItemId)
    {
        $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getParentItemId')->willReturn($parentItemId);
        $item->method('getProductType')->willReturn('simple');
        $item->method('getProductOptions')->willReturn([]);
        $item->method('getDiscountAmount')->willReturn($discount);
        $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);

        return $item;
    }

    /**
     * @param float  $discount
     * @param int    $itemId
     * @param string $productType
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeSimpleItem(float $discount, int $itemId, string $productType = 'simple')
    {
        $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getProductType')->willReturn($productType);
        $item->method('getProductOptions')->willReturn([]);
        $item->method('getDiscountAmount')->willReturn($discount);
        $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);

        return $item;
    }

    /**
     * A capture nominates reserved lines by ArticleNumber, so no two lines may share one. The
     * service cost line is article 1, and the discount lines used to be too - with a payment fee
     * or more than one VAT rate that produced duplicates the gateway cannot tell apart.
     */
    public function testDiscountLinesNeverShareANumberWithAnotherLine(): void
    {
        // BillinkHandler deliberately sends no global discount line, so use one that does.
        $this->instanceClass =
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler';
        $instance = $this->buildInstance(true);

        // Two VAT rates and nothing allocated to the items, so several discount lines are built.
        $items = [];
        foreach ([[100.0, 21.0], [50.0, 9.0]] as [$rowTotal, $vat]) {
            $item = $this->getFakeMock('Magento\Sales\Model\Order\Item')->getMock();
            $item->method('getDiscountAmount')->willReturn(0.0);
            $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
            $item->method('getRowTotal')->willReturn($rowTotal);
            $item->method('getTaxPercent')->willReturn($vat);
            $items[] = $item;
        }

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getDiscountAmount')->willReturn(-30.00);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $order->method('getShippingDiscountAmount')->willReturn(0.0);
        $order->method('getAllItems')->willReturn($items);
        $this->setProperty('order', $order, $instance);

        $lines = $instance->getDiscountLines();
        $identifiers = array_column($lines, 'identifier');

        $this->assertGreaterThan(1, count($lines), 'One discount line per VAT rate');
        $this->assertSame(
            $identifiers,
            array_unique($identifiers),
            'Every discount line needs its own ArticleNumber'
        );
        $this->assertNotContains('1', $identifiers, 'Article 1 belongs to the service cost line');
    }

    /**
     * A residual used to be absorbed by splitting a unit off an existing line, which
     * left two articles with the SAME ArticleNumber. Klarna could not tell them apart on a
     * partial capture: "Please make sure you also provide the Article Quantity for the items
     * with the same Article Number as in the Reserve!".
     *
     * @param float $target
     * @param bool  $mayFold
     * @param int   $expectedLines
     */
    #[DataProvider('residualProvider')]
    public function testAResidualNeverSplitsALine(float $target, bool $mayFold, int $expectedLines): void
    {
        $this->instanceClass = $mayFold
            ? 'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\BillinkHandler'
            : 'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler';
        $instance = $this->buildInstance(true);

        // One line of 3 x 31.87 = 95.61; no single-quantity line exists to carry a cent.
        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'description' => 'Bag', 'quantity' => 3,
             'price' => 31.87, 'vatPercentage' => 21.0],
        ]];

        $result = $this->invokeArgs('absorbRoundingResidual', [$articles, $target], $instance);
        $identifiers = array_column($result['articles'], 'identifier');

        $this->assertCount($expectedLines, $result['articles']);
        $this->assertSame($identifiers, array_unique($identifiers), 'No duplicate ArticleNumber');
        $this->assertEqualsWithDelta($target, $this->sumLines($result['articles']), 0.001);
    }

    public static function residualProvider(): array
    {
        return [
            // A cent short, with no single-quantity line able to carry it.
            'Klarna KP, which resolves a capture from the reserve' => [95.60, false, 2],
            // Every other method behaves identically - a real price is never rewritten.
            'a method that sends prices on a capture' => [95.60, true, 2],
        ];
    }

    /**
     * A price that belongs to a real line is never rewritten to swallow a cent, whatever the
     * method: the merchant's own records would no longer match what the shopper is shown.
     *
     * @param string $handlerClass
     */
    #[DataProvider('reserveHandlerProvider')]
    public function testARealLinePriceIsNeverRewritten(string $handlerClass): void
    {
        $this->instanceClass = $handlerClass;
        $instance = $this->buildInstance(true);

        // A shipping line of quantity 1 is exactly what the old code would have nudged.
        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'description' => 'Bag', 'quantity' => 3,
             'price' => 31.87, 'vatPercentage' => 21.0],
            ['identifier' => 2, 'description' => 'Shipping fee', 'quantity' => 1,
             'price' => 12.10, 'vatPercentage' => 21.0],
        ]];

        $result = $this->invokeArgs('absorbRoundingResidual', [$articles, 107.70], $instance);

        $this->assertEqualsWithDelta(
            12.10,
            (float)$result['articles'][1]['price'],
            0.001,
            'The shipping price must survive untouched'
        );
        $this->assertEqualsWithDelta(
            -0.01,
            (float)$result['articles'][2]['price'],
            0.001,
            'The cent rides on an adjustment line instead'
        );
        $this->assertEqualsWithDelta(107.70, $this->sumLines($result['articles']), 0.001);
    }

    /**
     * A residual larger than the tolerance is left alone and reported, never invented away.
     */
    public function testAResidualBeyondToleranceIsLeftAlone(): void
    {
        $this->instanceClass =
            'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler';
        $instance = $this->buildInstance(true);

        $articles = ['articles' => [
            ['identifier' => '24-WB01', 'description' => 'Bag', 'quantity' => 3,
             'price' => 31.87, 'vatPercentage' => 21.0],
        ]];

        $result = $this->invokeArgs('absorbRoundingResidual', [$articles, 90.00], $instance);

        $this->assertCount(1, $result['articles'], 'A 5.61 gap is a data problem, not rounding');
    }

    // -------------------------------------------------------------------------
    // The lines the reserve actually emits
    // -------------------------------------------------------------------------

    /**
     * The reserve sends one line per item, priced net of that item's discount and with no
     * separate discount article.
     *
     * @param string $handlerClass
     */
    #[DataProvider('reserveHandlerProvider')]
    public function testTheReserveEmitsOneNettedLinePerItem(string $handlerClass): void
    {
        $this->instanceClass = $handlerClass;
        $instance = $this->buildInstance(true);

        // EUR 35.36 gross per unit, 3 units, EUR 16.99 off the row.
        $quoteItem = $this->makeQuoteItem(35.36, 3.0, 16.99);

        $quote = $this->getFakeMock('Magento\Quote\Model\Quote')->getMock();
        $quote->method('getAllItems')->willReturn([$quoteItem]);
        $this->setProperty('quote', $quote, $instance);

        $lines = $this->invoke('getItemsLines', $instance);

        $this->assertCount(1, $lines, 'One line per item, no separate discount article');
        $this->assertSame('SKU-1', $lines[0]['identifier']);
        $this->assertSame(3, $lines[0]['quantity']);
        // 35.36 - (16.99 / 3) = 29.70 (rounded to the cent, as the article line is)
        $this->assertEqualsWithDelta(29.70, $lines[0]['price'], 0.005);
        // The line alone is the item's whole contribution: 3 x 29.70 == 106.08 - 16.99 + rounding.
        $this->assertEqualsWithDelta(
            round(3 * 35.36 - 16.99, 2),
            round(3 * $lines[0]['price'], 2),
            0.02,
            'The line must carry the row total net of discount'
        );
    }

    public static function reserveHandlerProvider(): array
    {
        return [
            // Billink inherits the base implementation.
            'base handler' =>
                ['Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\BillinkHandler'],
        ];
    }

    /**
     * An item without a discount is untouched.
     */
    public function testAnUndiscountedItemKeepsItsGrossPrice(): void
    {
        $instance = $this->buildInstance(true);

        $quote = $this->getFakeMock('Magento\Quote\Model\Quote')->getMock();
        $quote->method('getAllItems')->willReturn([$this->makeQuoteItem(35.36, 2.0, 0.0)]);
        $this->setProperty('quote', $quote, $instance);

        $lines = $this->invoke('getItemsLines', $instance);

        $this->assertEqualsWithDelta(35.36, $lines[0]['price'], 0.005);
    }

    /**
     * ArticlesHandlerFactory returns a SHARED handler and getQuote() caches the quote it loads,
     * so a second order handled in the same process must not inherit the first order's cart.
     */
    public function testSettingANewOrderDropsTheCachedQuote(): void
    {
        $instance = $this->buildInstance(true);

        $firstQuote = $this->getFakeMock('Magento\Quote\Model\Quote')->getMock();
        $this->setProperty('quote', $firstQuote, $instance);
        $this->assertSame($firstQuote, $instance->getQuote());

        $instance->setOrder($this->getFakeMock('Magento\Sales\Model\Order')->getMock());

        $this->assertNull(
            $this->getProperty('quote', $instance),
            'The cached quote must not survive a change of order'
        );
    }

    /**
     * @param float $grossUnitPrice
     * @param float $qty
     * @param float $rowDiscount
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeQuoteItem(float $grossUnitPrice, float $qty, float $rowDiscount)
    {
        $item = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteItemStub::class)
            ->getMock();
        $item->method('getRowTotalInclTax')->willReturn($grossUnitPrice * $qty);
        $item->method('hasParentItemId')->willReturn(false);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getProductType')->willReturn('simple');
        $item->method('getName')->willReturn('Test product');
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getTotalQty')->willReturn($qty);
        $item->method('getPriceInclTax')->willReturn($grossUnitPrice);
        $item->method('getTaxPercent')->willReturn(21.0);
        $item->method('getWeeeTaxAppliedAmount')->willReturn(0.0);
        $item->method('getDiscountAmount')->willReturn($rowDiscount);
        $item->method('getDiscountTaxCompensationAmount')->willReturn(0.0);

        return $item;
    }

    /**
     * @param array $lines
     *
     * @return float
     */
    private function sumLines(array $lines): float
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += (float)$line['price'] * (int)$line['quantity'];
        }

        return round($sum, 2);
    }

    /**
     * @param bool $includesTax
     *
     * @return object
     */
    private function buildInstance(bool $includesTax)
    {
        $instance = $this->getInstance();

        $scopeConfig = $this->getFakeMock('Magento\Framework\App\Config\ScopeConfigInterface')->getMock();
        $scopeConfig->method('getValue')->willReturn($includesTax ? '1' : '0');
        $this->setProperty('scopeConfig', $scopeConfig, $instance);

        $productMetaData = $this->getFakeMock('Magento\Framework\App\ProductMetadataInterface')->getMock();
        $productMetaData->method('getEdition')->willReturn('Community');
        $softwareData = $this->getFakeMock('Buckaroo\Magento2\Service\Software\Data')->getMock();
        $softwareData->method('getProductMetaData')->willReturn($productMetaData);
        $this->setProperty('softwareData', $softwareData, $instance);

        return $instance;
    }
}
