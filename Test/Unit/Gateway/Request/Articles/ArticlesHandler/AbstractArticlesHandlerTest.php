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

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\DefaultHandler;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\AttributeValue;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The article lines, amounts and VAT rates the shared handler sends for an order, a capture and a
 * refund. Built from real sales entities filled with data; every expected value is worked out by
 * hand from that data.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class AbstractArticlesHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    use ArticlesHandlerFixtures;

    protected $instanceClass = DefaultHandler::class;

    /**
     * @param Emulation|null $emulation
     *
     * @return DefaultHandler
     */
    private function handler(?Emulation $emulation = null): DefaultHandler
    {
        return new DefaultHandler(...$this->handlerArgs($emulation));
    }

    // ---------------------------------------------------------------- order (reserve / authorize)

    public function testAnOrderIsSentAsItemFeeShippingAndCreditLines(): void
    {
        $this->cart([
            // a configurable child: its parent line carries the money
            $this->quoteItem([
                'name' => 'Child', 'sku' => 'CHILD', 'qty' => '1.0000', 'parent_item_id' => 90,
                'price_incl_tax' => 24.20, 'row_total_incl_tax' => 24.20, 'tax_percent' => '21.0000',
            ]),
            $this->quoteItem([
                'name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => '2.0000', 'price_incl_tax' => 24.20,
                'row_total_incl_tax' => 48.40, 'tax_percent' => '21.0000', 'discount_amount' => 4.84,
            ]),
            // free gift
            $this->quoteItem([
                'name' => 'Gift', 'sku' => 'GIFT', 'qty' => '1.0000', 'price_incl_tax' => 0.0,
                'row_total_incl_tax' => 0.0, 'tax_percent' => '21.0000',
            ]),
            $this->quoteItem([
                'name' => 'Book', 'sku' => 'BOOK', 'qty' => '1.0000', 'price_incl_tax' => 10.90,
                'row_total_incl_tax' => 10.90, 'tax_percent' => '9.0000',
            ]),
        ]);

        $order = $this->order(
            [
                'grand_total' => 56.41, 'buckaroo_fee_incl_tax' => '2.5000', 'shipping_incl_tax' => '5.4500',
                'shipping_amount' => 5.00, 'shipping_tax_amount' => 0.45, 'discount_amount' => -4.84,
                'reward_currency_amount' => 1.00, 'gift_cards_amount' => 5.00,
            ],
            [
                $this->orderItem(['item_id' => 1, 'row_total' => 40.00, 'tax_percent' => 21.0, 'discount_amount' => 4.84]),
                $this->orderItem(['item_id' => 2, 'row_total' => 10.00, 'tax_percent' => 9.0]),
            ]
        );

        $this->assertSame(
            ['articles' => [
                $this->line('SHIRT', 'Shirt', 21.0, 2, 21.78),
                $this->line('BOOK', 'Book', 9.0, 1, 10.9),
                $this->line('1', 'Service Costs', 21.0, 1, 2.5),
                $this->line('2', 'Shipping fee', 9.0, 1, 5.45),
                $this->line('5', 'Discount Reward Points', 0.0, 1, -1.0),
                $this->line('6', 'Discount Gift Card', 0.0, 1, -5.0),
            ]],
            $this->handler()->getOrderArticlesData($order, $this->payment())
        );
    }

    /**
     * @param string $edition
     * @param float  $discount
     * @param float  $grandTotal
     */
    #[DataProvider('orderDiscountProvider')]
    public function testAnUnallocatedDiscountAndStoreCreditGoOnADiscountLine(
        string $edition,
        float $discount,
        float $grandTotal
    ): void {
        $this->edition = $edition;
        $this->cart([
            $this->quoteItem([
                'name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => '1.0000', 'price_incl_tax' => 100.00,
                'row_total_incl_tax' => 100.00, 'tax_percent' => '21.0000',
            ]),
        ]);
        $order = $this->order(
            ['grand_total' => $grandTotal, 'discount_amount' => -10.00, 'customer_balance_amount' => 5.00],
            [$this->orderItem(['item_id' => 1, 'row_total' => 82.64, 'tax_percent' => 21.0])]
        );

        $this->assertSame(
            ['articles' => [
                $this->line('SHIRT', 'Shirt', 21.0, 1, 100.0),
                $this->line('discount', 'Discount', 21.0, 1, $discount),
            ]],
            $this->handler()->getOrderArticlesData($order, $this->payment())
        );
    }

    public static function orderDiscountProvider(): array
    {
        return [
            'store credit only exists on Enterprise' => ['Community', -10.0, 90.00],
            'store credit is added to the discount' => ['Enterprise', -15.0, 85.00],
        ];
    }

    /**
     * @param array  $items
     * @param array  $orderData
     * @param bool   $pricesIncludeTax
     * @param string $edition
     * @param array  $expected
     */
    #[DataProvider('discountLinesProvider')]
    public function testTheDiscountIsSplitOverTheVatRatesOfThePricedItems(
        array $items,
        array $orderData,
        bool $pricesIncludeTax,
        string $edition,
        array $expected
    ): void {
        if (!$pricesIncludeTax) {
            $this->pricesExcludeTax();
        }
        $this->edition = $edition;

        $handler = $this->handler();
        $handler->setOrder($this->order($orderData, array_map(fn($data) => $this->orderItem($data), $items)));

        $lines = array_map(
            fn($line) => [$line['identifier'], $line['description'], $line['vatPercentage'], $line['quantity'], $line['price']],
            $handler->getDiscountLines()
        );

        $this->assertSame($expected, $lines);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public static function discountLinesProvider(): array
    {
        $item = fn(int $id, float $rowTotal, float $vat, array $extra = []) =>
            ['item_id' => $id, 'row_total' => $rowTotal, 'tax_percent' => $vat] + $extra;

        return [
            'three rates, the last takes the remainder' => [
                [$item(1, 33.33, 21), $item(2, 33.33, 9), $item(3, 33.34, 6)],
                ['discount_amount' => -10.00], true, 'Community',
                [
                    ['discount-21', 'Discount', 21.0, 1, -3.33],
                    ['discount-9', 'Discount', 9.0, 1, -3.33],
                    ['discount-6', 'Discount', 6.0, 1, -3.34],
                ],
            ],
            'a one cent share is still sent' => [
                [$item(1, 1.00, 21), $item(2, 99.00, 9)],
                ['discount_amount' => -1.00], true, 'Community',
                [['discount-21', 'Discount', 21.0, 1, -0.01], ['discount-9', 'Discount', 9.0, 1, -0.99]],
            ],
            'an item without a row total has no share' => [
                [$item(1, 0.0, 6), $item(2, 50.00, 21), $item(3, 50.00, 9)],
                ['discount_amount' => -10.00], true, 'Community',
                [['discount-21', 'Discount', 21.0, 1, -5.0], ['discount-9', 'Discount', 9.0, 1, -5.0]],
            ],
            'an item without a row total does not add a rate' => [
                [$item(1, 0.0, 9), $item(2, 100.00, 21)],
                ['discount_amount' => -10.00], true, 'Community',
                [['discount', 'Discount', 21.0, 1, -10.0]],
            ],
            'no priced items leaves the discount without VAT' => [
                [$item(1, 0.0, 21)],
                ['discount_amount' => -10.00], true, 'Community',
                [['discount', 'Discount', 0.0, 1, -10.0]],
            ],
            'configurable children and orphans are not counted' => [
                [
                    $item(1, 50.00, 21, ['product_type' => 'configurable']),
                    $item(2, 50.00, 9, ['parent_item_id' => 1]),
                    $item(3, 20.00, 6, ['parent_item_id' => 999]),
                    $item(4, 30.00, 9),
                ],
                ['discount_amount' => -8.00], true, 'Community',
                [['discount-21', 'Discount', 21.0, 1, -5.0], ['discount-9', 'Discount', 9.0, 1, -3.0]],
            ],
            'a dynamic bundle is priced by its children' => [
                [
                    $item(1, 100.00, 0, ['product_type' => 'bundle', 'product_options' => ['product_calculations' => '0']]),
                    $item(2, 60.00, 21, ['parent_item_id' => 1]),
                    $item(3, 40.00, 9, ['parent_item_id' => 1]),
                ],
                ['discount_amount' => -10.00], true, 'Community',
                [['discount-21', 'Discount', 21.0, 1, -6.0], ['discount-9', 'Discount', 9.0, 1, -4.0]],
            ],
            'a one cent unallocated discount' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00])],
                ['discount_amount' => -10.01], true, 'Community',
                [['discount', 'Discount', 21.0, 1, -0.01]],
            ],
            'a sub-cent unallocated amount rounds to a cent' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00])],
                ['discount_amount' => -10.005], true, 'Community',
                [['discount', 'Discount', 21.0, 1, -0.01]],
            ],
            'a fully allocated discount has no line' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00])],
                ['discount_amount' => -12.00, 'shipping_discount_amount' => -2.00], true, 'Community',
                [],
            ],
            'tax compensation counts when prices exclude tax' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00, 'discount_tax_compensation_amount' => 1.00])],
                ['discount_amount' => -10.00, 'discount_tax_compensation_amount' => 2.10], false, 'Community',
                [['discount', 'Discount', 21.0, 1, -1.1]],
            ],
            'tax compensation is ignored when prices include tax' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00, 'discount_tax_compensation_amount' => 1.00])],
                ['discount_amount' => -10.00, 'discount_tax_compensation_amount' => 2.10], true, 'Community',
                [],
            ],
            'missing item tax compensation counts as none' => [
                [$item(1, 100.00, 21, ['discount_amount' => 10.00, 'discount_tax_compensation_amount' => null])],
                ['discount_amount' => -10.00, 'discount_tax_compensation_amount' => 1.00], false, 'Community',
                [['discount', 'Discount', 21.0, 1, -1.0]],
            ],
            'one cent of store credit' => [
                [$item(1, 100.00, 21)],
                ['customer_balance_amount' => 0.01], true, 'Enterprise',
                [['discount', 'Discount', 21.0, 1, -0.01]],
            ],
        ];
    }

    public function testDynamicAndFixedBundlesAreSentAsTheLinesThatCarryTheMoney(): void
    {
        $dynamic = $this->product('bundle', '0');
        $withoutPriceType = $this->product('bundle', null);

        $this->cart([
            $this->quoteItem(['item_id' => 1, 'name' => 'Bundle', 'sku' => 'BUNDLE', 'qty' => 1,
                'price_incl_tax' => 29.05, 'row_total_incl_tax' => 29.05, 'tax_percent' => '21.0000'], $dynamic),
            $this->quoteItem(['name' => 'Part A', 'sku' => 'PA', 'qty' => '1.0000', 'parent_item_id' => 1,
                'price_incl_tax' => 18.15, 'row_total_incl_tax' => 18.15, 'tax_percent' => '21.0000']),
            $this->quoteItem(['name' => 'Part B', 'sku' => 'PB', 'qty' => '1.0000', 'parent_item_id' => 1,
                'price_incl_tax' => 10.90, 'row_total_incl_tax' => 10.90, 'tax_percent' => '9.0000']),
            $this->quoteItem(['item_id' => 4, 'name' => 'Fixed', 'sku' => 'FIXED', 'qty' => '1.0000',
                'price_incl_tax' => 20.00, 'row_total_incl_tax' => 20.00, 'tax_percent' => '21.0000'], $withoutPriceType),
            $this->quoteItem(['name' => 'Sock', 'sku' => 'SOCK', 'qty' => '1.0000',
                'price_incl_tax' => 5.00, 'row_total_incl_tax' => 5.00, 'tax_percent' => '21.0000']),
            $this->quoteItem(['name' => 'Variant', 'sku' => 'VAR', 'qty' => '1.0000', 'parent_item_id' => 40,
                'price_incl_tax' => 12.10, 'row_total_incl_tax' => 12.10, 'tax_percent' => '21.0000']),
        ]);

        $this->assertSame(
            ['articles' => [
                $this->line('PA', 'Part A', 21.0, 1, 18.15),
                $this->line('PB', 'Part B', 9.0, 1, 10.9),
                $this->line('FIXED', 'Fixed', 21.0, 1, 20.0),
                $this->line('SOCK', 'Sock', 21.0, 1, 5.0),
            ]],
            $this->handler()->getOrderArticlesData($this->order(['grand_total' => 54.05]), $this->payment())
        );
    }

    public function testAtMostNinetyNineItemLinesAreSent(): void
    {
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = $this->quoteItem(['name' => 'P' . $i, 'sku' => 'P' . $i, 'qty' => '1.0000',
                'price_incl_tax' => 1.00, 'row_total_incl_tax' => 1.00, 'tax_percent' => '21.0000']);
        }
        $this->cart($items);

        $articles = $this->handler()->getOrderArticlesData($this->order(['grand_total' => 99.00]), $this->payment());

        $this->assertCount(99, $articles['articles']);
        $this->assertSame('P99', end($articles['articles'])['identifier']);
    }

    /**
     * @param float $grandTotal
     * @param array $lastLine
     */
    #[DataProvider('orderResidualProvider')]
    public function testARoundingResidualOfTheOrderGetsALineOfItsOwn(float $grandTotal, array $lastLine): void
    {
        $this->cart([
            $this->quoteItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => '3.0000', 'price_incl_tax' => 10.00,
                'row_total_incl_tax' => 30.00, 'tax_percent' => '21.0000', 'discount_amount' => 10.00]),
        ]);

        $articles = $this->handler()->getOrderArticlesData(
            $this->order(['grand_total' => $grandTotal, 'tax_amount' => 0.0]),
            $this->payment()
        );

        $this->assertSame(
            ['articles' => [$this->line('SHIRT', 'Shirt', 21.0, 3, 6.67), $lastLine]],
            $articles
        );
    }

    public static function orderResidualProvider(): array
    {
        return [
            'one cent below the lines' => [20.00, ['identifier' => 'adjustment', 'description' => 'Adjustment',
                'vatPercentage' => 0.0, 'quantity' => 1, 'price' => -0.01]],
            'five cents is still rounding' => [20.06, ['identifier' => 'adjustment', 'description' => 'Adjustment',
                'vatPercentage' => 0.0, 'quantity' => 1, 'price' => 0.05]],
            'six cents is reconciled as a fee' => [20.07, ['identifier' => 'extra-fees', 'description' => 'Extra Fees',
                'vatPercentage' => 0.0, 'quantity' => 1, 'price' => 0.06]],
        ];
    }

    public function testAZeroOrderTotalIsNotTreatedAsRounding(): void
    {
        $this->cart([
            $this->quoteItem(['name' => 'Pin', 'sku' => 'PIN', 'qty' => '1.0000', 'price_incl_tax' => 0.02,
                'row_total_incl_tax' => 0.02, 'tax_percent' => '0.0000']),
        ]);

        $articles = $this->handler()->getOrderArticlesData(
            $this->order(['grand_total' => 0.0, 'tax_amount' => 0.0]),
            $this->payment()
        );

        $this->assertSame(
            ['articles' => [
                $this->line('PIN', 'Pin', 0.0, 1, 0.02),
                $this->line('extra-fees', 'Extra Fees', 0.0, 1, -0.02),
            ]],
            $articles
        );
    }

    public function testAnAdjustmentIsNotAddedBeyondTheArticleLimit(): void
    {
        $handler = $this->handler();
        $handler->setOrder($this->order([]));

        $lines = array_fill(0, 99, $this->line('P', 'P', 21.0, 1, 1.0));
        $this->assertSame(
            ['articles' => $lines],
            $this->invokeArgs('absorbRoundingResidual', [['articles' => $lines], 99.03], $handler)
        );

        $lines = array_fill(0, 98, $this->line('P', 'P', 21.0, 1, 1.0));
        $result = $this->invokeArgs('absorbRoundingResidual', [['articles' => $lines], 98.03], $handler);
        $this->assertSame($this->line('adjustment', 'Adjustment', 0.0, 1, 0.03), end($result['articles']));
    }

    public function testAPayRemainderOrderIsOneLineForTheRemainingAmount(): void
    {
        $this->isPayRemainder = true;
        $this->payRemainder = 12.344;

        $this->assertSame(
            ['articles' => [0 => $this->line('1', 'PayRemainder', 21.0, 1, 12.34)]],
            $this->handler()->getOrderArticlesData($this->order([]), $this->payment())
        );
    }

    // ---------------------------------------------------------------- reconciliation

    /**
     * @param float      $grandTotal
     * @param float|null $taxAmount
     * @param array|null $added
     */
    #[DataProvider('reconcileProvider')]
    public function testAnUnaccountedAmountIsReconciledWithItsResidualVatRate(
        float $grandTotal,
        ?float $taxAmount,
        ?array $added
    ): void {
        $handler = $this->handler();
        $handler->setOrder($this->order(['tax_amount' => '22.9000']));

        $lines = [
            $this->line('free', 'Free', 0.0, 1, 0.0),
            $this->line('A', 'A', 21.0, 2, 60.5),
            $this->line('B', 'B', 9.0, 1, 10.9),
        ];
        $expected = $added === null ? $lines : array_merge($lines, [$added]);

        $this->assertSame(
            ['articles' => $expected],
            $this->invokeArgs(
                'reconcileArticlesWithGrandTotal',
                [['articles' => $lines], $grandTotal, $taxAmount],
                $handler
            )
        );
    }

    public static function reconcileProvider(): array
    {
        $fee = fn(float $vat, float $price) => [
            'identifier' => 'extra-fees', 'description' => 'Extra Fees', 'vatPercentage' => $vat,
            'quantity' => 1, 'price' => $price,
        ];

        return [
            // the lines carry 21.00 + 0.90 tax; 1.00 of the 22.90 is left for 7.00 net
            'rate from the order tax' => [139.90, null, $fee(14.2857, 8.0)],
            // 0.70 tax on 7.30 net
            'rate from the given tax' => [139.90, 22.60, $fee(9.589, 8.0)],
            'more tax than the residual carries' => [139.90, 40.00, $fee(0.0, 8.0)],
            'a residual without tax' => [139.90, 21.90, $fee(0.0, 8.0)],
            'four cents' => [131.94, null, $fee(0.0, 0.04)],
            'one cent is left alone' => [131.91, null, null],
            'a matching total is left alone' => [131.90, null, null],
        ];
    }

    public function testIncompleteLinesAreSummedWithDefaults(): void
    {
        $handler = $this->handler();
        $handler->setOrder($this->order(['tax_amount' => 0.0]));

        $articles = ['articles' => [['price' => 2.5], ['quantity' => 3], 'not a line', ['price' => 1.25, 'quantity' => 2]]];

        $this->assertSame(5.0, $this->invokeArgs('sumArticleLines', [$articles], $handler));
        $this->assertSame(
            $articles,
            $this->invokeArgs('reconcileArticlesWithGrandTotal', [$articles, 5.0], $handler)
        );
    }

    // ---------------------------------------------------------------- service cost / payment fee

    /**
     * @param mixed      $feeInclTax
     * @param mixed      $fee
     * @param float|null $expected
     */
    #[DataProvider('serviceCostProvider')]
    public function testThePaymentFeeIsSentInclTaxWithTheFeeTaxRate($feeInclTax, $fee, ?float $expected): void
    {
        $order = $this->order(['buckaroo_fee_incl_tax' => $feeInclTax, 'buckaroo_fee' => $fee]);
        $handler = $this->handler();

        $total = 10.0;
        $line = $handler->getServiceCostLine($order, $total);
        $refundTotal = 10.0;
        $refundLine = $handler->getServiceCostLine($order, $refundTotal, true);

        if ($expected === null) {
            $this->assertSame([], $line);
            $this->assertSame([], $refundLine);
            $this->assertSame(10.0, $total);
            return;
        }

        $article = $this->line('1', 'Service Costs', 21.0, 1, $expected);
        $this->assertSame(['articles' => [$article]], $line);
        $this->assertSame(['articles' => [$article + ['refundType' => 'Refund']]], $refundLine);
        $this->assertEqualsWithDelta(10.0 + $expected, $total, 0.0001);
        $this->assertEqualsWithDelta(10.0 + $expected, $refundTotal, 0.0001);
    }

    public static function serviceCostProvider(): array
    {
        return [
            'fee incl tax wins over the fee' => ['2.5000', 2.07, 2.5],
            'fee incl tax without a fee' => [2.50, 0.0, 2.5],
            'the fee when there is no fee incl tax' => [0.0, '2.0700', 2.07],
            'a one cent fee' => [0.0, 0.01, 0.01],
            'a sub-cent fee is ignored' => [0.0, 0.009, null],
            'a negative fee is not sent' => [-2.50, 0.0, null],
            'no fee' => [null, null, null],
        ];
    }

    public function testFeeLabelsAreRenderedInTheOrderStore(): void
    {
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects($this->once())->method('startEnvironmentEmulation')->with(3, 'frontend');
        $emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $handler = $this->handler($emulation);
        $handler->setOrder($this->order(['store_id' => '3']));

        $line = $handler->getServiceCostLine($this->order(['buckaroo_fee_incl_tax' => 1.0]));

        $this->assertSame('Service Costs', $line['articles'][0]['description']);
    }

    public function testTheStoreEmulationEndsWhenRenderingFails(): void
    {
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects($this->once())->method('startEnvironmentEmulation');
        $emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $handler = $this->handler($emulation);
        $handler->setOrder($this->order(['store_id' => 3]));

        $this->expectException(\RuntimeException::class);
        $this->invokeArgs('renderInOrderStoreLocale', [function () {
            throw new \RuntimeException('render failed');
        }], $handler);
    }

    // ---------------------------------------------------------------- shipping

    /**
     * @param array $data
     * @param bool  $includesTax
     * @param float $expected
     */
    #[DataProvider('shippingProvider')]
    public function testTheShippingLineIsTheGrossCostAfterItsDiscount(array $data, bool $includesTax, float $expected): void
    {
        if (!$includesTax) {
            $this->pricesExcludeTax();
        }
        $order = $this->order($data);
        $handler = $this->handler();
        $handler->setOrder($order);

        $this->assertSame($expected, $this->invokeArgs('getDiscountedShippingAmount', [$order], $handler));
    }

    public static function shippingProvider(): array
    {
        return [
            'no discount' => [['shipping_incl_tax' => '12.1000'], true, 12.1],
            'no discount, sub-cent amount' => [['shipping_incl_tax' => 12.1049], true, 12.1],
            'a one cent discount' => [[
                'shipping_incl_tax' => 12.10, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 2.10,
                'shipping_discount_amount' => -0.01,
            ], true, 12.09],
            'discount with tax compensation' => [[
                'shipping_incl_tax' => 12.10, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 1.05,
                'shipping_discount_amount' => -5.00, 'shipping_discount_tax_compensation_amount' => 0.20,
            ], true, 6.25],
            'discount excl tax without compensation' => [[
                'shipping_incl_tax' => 12.10, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 1.05,
                'shipping_discount_amount' => -5.00,
            ], false, 6.05],
            'the result is rounded to the cent' => [[
                'shipping_incl_tax' => 12.10, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 1.0549,
                'shipping_discount_amount' => -5.00,
            ], true, 6.05],
            'the discount is rounded to the cent' => [[
                'shipping_incl_tax' => 10.00, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 0.0,
                'shipping_discount_amount' => -1.2351,
            ], false, 8.76],
            'a fully discounted shipping costs nothing' => [[
                'shipping_incl_tax' => 5.50, 'shipping_amount' => 5.00, 'shipping_tax_amount' => 0.50,
                'shipping_discount_amount' => -5.00,
            ], true, 0.0],
        ];
    }

    public function testAnInvoiceTakesTheShippingDiscountFromTheOrder(): void
    {
        $handler = $this->handler();
        $handler->setOrder($this->order(['shipping_discount_amount' => -5.00, 'shipping_amount' => 10.00]));
        $invoice = $this->invoice(
            ['shipping_incl_tax' => 12.10, 'shipping_amount' => 10.00, 'shipping_tax_amount' => 1.05],
            []
        );

        $this->assertSame(6.05, $this->invokeArgs('getDiscountedShippingAmount', [$invoice], $handler));
    }

    // ---------------------------------------------------------------- item prices

    /**
     * @param array $data
     * @param bool  $includesTax
     * @param float $expected
     */
    #[DataProvider('productPriceProvider')]
    public function testTheProductPriceIsTheGrossUnitPrice(array $data, bool $includesTax, float $expected): void
    {
        if (!$includesTax) {
            $this->pricesExcludeTax();
        }

        $this->assertEqualsWithDelta(
            $expected,
            $this->handler()->calculateProductPrice($this->orderItem($data)),
            0.00001
        );
    }

    public static function productPriceProvider(): array
    {
        return [
            'price incl tax' => [['price_incl_tax' => 12.10], true, 12.10],
            'with fixed product tax' => [['price_incl_tax' => 12.10, 'weee_tax_applied_amount' => 1.50], true, 13.60],
            'excl tax with a one cent discount uses the discounted tax' => [[
                'price_incl_tax' => 12.10, 'price' => 10.00, 'tax_amount' => 4.18, 'qty_ordered' => 2,
                'discount_amount' => 0.01,
            ], false, 12.09],
        ];
    }

    public function testTheTaxOfAChildIsSpreadOverItsTotalQuantity(): void
    {
        $this->pricesExcludeTax();

        $parent = $this->quoteItem(['qty' => 2]);
        $child = $this->quoteItem(['qty' => 1, 'price_incl_tax' => 12.10, 'price' => 10.00, 'tax_amount' => 4.20,
            'discount_amount' => 1.00]);
        $child->setParentItem($parent);

        $this->assertEqualsWithDelta(12.10, $this->handler()->calculateProductPrice($child), 0.00001);
    }

    /**
     * @param array $data
     * @param bool  $includesTax
     * @param float $qty
     * @param float $expected
     */
    #[DataProvider('unitDiscountProvider')]
    public function testTheUnitDiscount(array $data, bool $includesTax, float $qty, float $expected): void
    {
        if (!$includesTax) {
            $this->pricesExcludeTax();
        }

        $this->assertEqualsWithDelta(
            $expected,
            $this->invokeArgs('getUnitDiscount', [$this->orderItem($data), $qty], $this->handler()),
            0.00001
        );
    }

    public static function unitDiscountProvider(): array
    {
        return [
            'a one cent discount' => [['discount_amount' => 0.01], true, 1.0, 0.01],
            'excl tax without compensation' => [['discount_amount' => 2.00], false, 1.0, 2.0],
        ];
    }

    // ---------------------------------------------------------------- article line format

    public function testTheArticleLineNormalisesItsAmounts(): void
    {
        $handler = $this->handler();

        $this->assertSame(
            ['identifier' => '5', 'description' => 'Thing', 'vatPercentage' => 21.0, 'quantity' => 3, 'price' => 1234.5],
            $handler->getArticleArrayLine('Thing', 5, 2.5, '1,234.50', '21.0000')
        );
        $this->assertSame(
            ['identifier' => '', 'description' => null, 'vatPercentage' => 0.0, 'quantity' => 2, 'price' => 1.01],
            $handler->getArticleArrayLine(null, null, 2.4, 1.006, '')
        );
        $this->assertSame(
            ['refundType' => 'Refund', 'identifier' => '5', 'description' => 'Thing', 'vatPercentage' => 9.0,
                'quantity' => 3, 'price' => 1234.5],
            $handler->getArticleRefundArrayLine('Thing', 5, 2.5, '1,234.50', 9)
        );
        $this->assertSame(
            ['refundType' => 'Refund', 'identifier' => '1', 'description' => null, 'vatPercentage' => 0.0,
                'quantity' => 2, 'price' => 1.01],
            $handler->getArticleRefundArrayLine(null, 1, 2.4, 1.006, null)
        );
    }

    /**
     * @param mixed $amount
     * @param float $expected
     */
    #[DataProvider('amountProvider')]
    public function testAmountsAreNormalisedToFloats($amount, float $expected): void
    {
        $this->assertSame($expected, $this->invokeArgs('normalizeAmount', [$amount], $this->handler()));
    }

    public static function amountProvider(): array
    {
        return [
            'null' => [null, 0.0],
            'empty' => ['', 0.0],
            'int' => [5, 5.0],
            'float' => [1.5, 1.5],
            'decimal string' => [' 20.0000 ', 20.0],
            'thousands separator' => ['1,234.50', 1234.5],
            'negative with thousands separator' => ['-12,345,678', -12345678.0],
            'thousands separator with surrounding spaces' => [' 1,234.50 ', 1234.5],
        ];
    }

    /**
     * @param string $amount
     */
    #[DataProvider('invalidAmountProvider')]
    public function testANonNumericAmountIsRefused(string $amount): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->invokeArgs('normalizeAmount', [$amount], $this->handler());
    }

    public static function invalidAmountProvider(): array
    {
        return [
            'decimal comma' => ['1.234,56'],
            'a group of two digits' => ['1,234,56'],
            'a leading group of four digits' => ['1234,567'],
            'text' => ['abc'],
        ];
    }

    public function testThePublicLabelsAndCreditLines(): void
    {
        $handler = $this->handler();
        $payment = $this->payment();
        $handler->setPayment($payment);
        $handler->setOrder($this->order(['reward_currency_amount' => 2.5, 'gift_cards_amount' => 7.25]));

        $this->assertSame($payment, $handler->getPayment());
        $this->assertSame('Discount', (string)$handler->getDiscount());
        $this->assertSame('Shipping fee', (string)$handler->getShippingFee());
        $this->assertSame('Service Costs', (string)$handler->getServiceCosts());
        $this->assertSame('Adjustment', (string)$handler->getAdjustmentLabel());
        $this->assertSame($this->line('5', 'Discount Reward Points', 0.0, 1, -2.5), $handler->getRewardLine());
        $this->assertSame($this->line('6', 'Discount Gift Card', 0.0, 1, -7.25), $handler->getGiftCardLine());

        $handler->setOrder($this->order([]));
        $this->assertSame([], $handler->getRewardLine());
        $this->assertSame([], $handler->getGiftCardLine());
    }

    // ---------------------------------------------------------------- capture (invoice)

    /**
     * @param bool  $canInvoice
     * @param array $tail
     */
    #[DataProvider('captureProvider')]
    public function testTheFirstCaptureSendsItsItemsAtReservedPrices(bool $canInvoice, array $tail): void
    {
        [$order, $invoice] = $this->captureScenario($canInvoice);
        $invoice->setData('buckaroo_fee_incl_tax', 2.50);

        $this->assertSame(
            ['articles' => array_merge([
                $this->line('SHIRT', 'Shirt', 21.0, 3, 6.67),
                $this->line('MUG', 'Mug', 21.0, 3, 16.67),
                $this->line('BOOK', 'Book', 9.0, 1, 10.9),
                $this->line('1', 'Service Costs', 21.0, 1, 2.5),
                $this->line('2', 'Shipping fee', 9.0, 1, 5.45),
            ], $tail)],
            $this->handler()->getInvoiceArticlesData($order, $this->payment())
        );
    }

    public static function captureProvider(): array
    {
        return [
            'the closing capture settles the reserve rounding' => [false, [[
                'identifier' => 'adjustment', 'description' => 'Adjustment', 'vatPercentage' => 0.0,
                'quantity' => 1, 'price' => -0.02,
            ]]],
            'an intermediate capture leaves it' => [true, []],
        ];
    }

    /**
     * @param int        $residualItems
     * @param float|null $adjustment
     */
    #[DataProvider('closingResidualProvider')]
    public function testTheClosingCaptureOnlySettlesARoundingSizedResidual(int $residualItems, ?float $adjustment): void
    {
        $orderItems = [];
        $invoiceItems = [];
        for ($i = 1; $i <= $residualItems; $i++) {
            $orderItem = $this->orderItem(['item_id' => $i, 'qty_ordered' => 3, 'price_incl_tax' => 10.00,
                'discount_amount' => 10.00, 'tax_percent' => 21.0, 'row_total' => 24.79]);
            $orderItems[] = $orderItem;
            $invoiceItems[] = $this->invoiceItem(['name' => 'S' . $i, 'sku' => 'S' . $i, 'qty' => 3,
                'row_total_incl_tax' => 20.00], $orderItem);
        }
        $invoice = $this->invoice(['entity_id' => 10], $invoiceItems);
        $order = $this->order(['discount_amount' => -10.00 * $residualItems], $orderItems, [$invoice], false);

        $articles = $this->handler()->getInvoiceArticlesData($order, $this->payment());

        $last = end($articles['articles']);
        if ($adjustment === null) {
            $this->assertCount($residualItems, $articles['articles']);
            return;
        }
        $this->assertSame($this->line('adjustment', 'Adjustment', 0.0, 1, $adjustment), $last);
    }

    public static function closingResidualProvider(): array
    {
        return [
            'five cents' => [5, -0.05],
            'six cents is not rounding' => [6, null],
        ];
    }

    public function testALaterCaptureCarriesItsOwnStoreCreditButNotTheFee(): void
    {
        $this->edition = 'Enterprise';

        $book = $this->orderItem(['item_id' => 1, 'qty_ordered' => 2, 'price_incl_tax' => 10.90,
            'tax_percent' => 9.0, 'row_total' => 20.00]);
        $first = $this->invoice(['entity_id' => 10], []);
        $second = $this->invoice(
            ['entity_id' => 11, 'buckaroo_fee_incl_tax' => 2.50, 'customer_balance_amount' => 0.01],
            [$this->invoiceItem(['name' => 'Book', 'sku' => 'BOOK', 'qty' => 1, 'row_total_incl_tax' => 10.90], $book)]
        );
        $order = $this->order(['customer_balance_amount' => 5.00], [$book], [$first, $second], true);

        $this->assertSame(
            ['articles' => [
                $this->line('BOOK', 'Book', 9.0, 1, 10.9),
                $this->line('discount', 'Discount', 9.0, 1, -0.01),
            ]],
            $this->handler()->getInvoiceArticlesData($order, $this->payment())
        );
    }

    public function testACaptureSendsAtMostNinetyNineItemLines(): void
    {
        $orderItems = [];
        $invoiceItems = [];
        for ($i = 1; $i <= 100; $i++) {
            $orderItem = $this->orderItem(['item_id' => $i, 'qty_ordered' => 1, 'price_incl_tax' => 1.00,
                'tax_percent' => 21.0, 'row_total' => 0.83]);
            $orderItems[] = $orderItem;
            $invoiceItems[] = $this->invoiceItem(['name' => 'P' . $i, 'sku' => 'P' . $i, 'qty' => 1,
                'row_total_incl_tax' => 1.00], $orderItem);
        }
        $invoice = $this->invoice(['entity_id' => 10], $invoiceItems);
        $order = $this->order([], $orderItems, [$invoice], true);

        $articles = $this->handler()->getInvoiceArticlesData($order, $this->payment());

        $this->assertCount(99, $articles['articles']);
        $this->assertSame('P99', end($articles['articles'])['identifier']);
    }

    // ---------------------------------------------------------------- refund (credit memo)

    /**
     * @param bool  $hasCreditmemos
     * @param float $grandTotal
     * @param bool  $withFee
     * @param float|null $diff
     */
    #[DataProvider('refundProvider')]
    public function testARefundSendsItsItemsFeeShippingAndAnyDifference(
        bool $hasCreditmemos,
        float $grandTotal,
        bool $withFee,
        ?float $diff
    ): void {
        $shirt = $this->orderItem(['tax_percent' => '21.0000']);
        $book = $this->orderItem(['tax_percent' => '9.0000']);
        $creditmemo = $this->creditmemo(
            ['grand_total' => $grandTotal, 'buckaroo_fee_incl_tax' => 2.50, 'shipping_incl_tax' => 5.45],
            [
                $this->creditmemoItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => 3, 'price_incl_tax' => 10.00,
                    'row_total_incl_tax' => 30.00, 'discount_amount' => 5.00], $shirt),
                $this->creditmemoItem(['name' => 'Child', 'sku' => 'CHILD', 'qty' => 1, 'price_incl_tax' => 5.00,
                    'row_total_incl_tax' => 5.00, 'parent_item_id' => 7], $shirt),
                $this->creditmemoItem(['name' => 'Book', 'sku' => 'BOOK', 'qty' => 1, 'price_incl_tax' => 10.90,
                    'row_total_incl_tax' => 10.90, 'discount_amount' => 0.0], $book),
            ]
        );
        $order = $this->order([], [], [], false, $hasCreditmemos);

        $expected = [
            $this->refund($this->line('SHIRT', 'Shirt', 21.0, 3, 8.33)),
            $this->refund($this->line('BOOK', 'Book', 9.0, 1, 10.9)),
        ];
        if ($withFee) {
            $expected[] = $this->line('1', 'Service Costs', 21.0, 1, 2.5) + ['refundType' => 'Refund'];
        }
        $expected[] = $this->line('2', 'Shipping fee', 9.0, 1, 5.45) + ['refundType' => 'Refund'];
        if ($diff !== null) {
            $expected[] = $this->line('4', 'Discount/Fee', 4.0, 1, $diff) + ['refundType' => 'Refund'];
        }

        $this->assertSame(
            ['articles' => $expected],
            $this->handler()->getCreditMemoArticlesData($order, $this->payment($order, $creditmemo))
        );
    }

    public static function refundProvider(): array
    {
        return [
            // 3 x 8.33 + 10.90 + 2.50 + 5.45 = 43.84
            'first refund with the fee and a difference' => [false, 43.90, true, 0.06],
            // 3 x 8.33 + 10.90 + 5.45 = 41.34
            'a later refund without fee that adds up' => [true, 41.34, false, null],
        ];
    }

    public function testARefundSendsAtMostNinetyNineItemLines(): void
    {
        $orderItem = $this->orderItem(['tax_percent' => 21.0]);
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = $this->creditmemoItem(['name' => 'P' . $i, 'sku' => 'P' . $i, 'qty' => 1,
                'price_incl_tax' => 1.00, 'row_total_incl_tax' => 1.00, 'discount_amount' => 0.0], $orderItem);
        }
        $creditmemo = $this->creditmemo(['grand_total' => 99.00], $items);
        $order = $this->order([], [], [], false, true);

        $articles = $this->handler()->getCreditMemoArticlesData($order, $this->payment($order, $creditmemo));

        $this->assertCount(99, $articles['articles']);
        $this->assertSame('P99', end($articles['articles'])['identifier']);
    }

    public function testAPayRemainderRefundIsOneLineForTheRemainingAmount(): void
    {
        $this->isPayRemainder = true;
        $this->payRemainder = 12.344;

        $this->assertSame(
            ['articles' => [0 => $this->refund($this->line('1', 'PayRemainder', 21.0, 1, 12.34))]],
            $this->handler()->getCreditMemoArticlesData($this->order([]), $this->payment())
        );
    }

    // ---------------------------------------------------------------- helpers

    /**
     * An order of three discounted items captured in one invoice, plus a cancelled line.
     *
     * Shirt and Mug each reserve at a unit price a third of a cent above the exact one, so the
     * reserve carried -0.02 of rounding.
     *
     * @param bool $canInvoice
     *
     * @return array
     */
    private function captureScenario(bool $canInvoice): array
    {
        $cancelled = $this->orderItem(['item_id' => 2, 'qty_ordered' => 0, 'price_incl_tax' => 5.00,
            'discount_amount' => 1.00, 'tax_percent' => 21.0, 'row_total' => 0.0]);
        $shirt = $this->orderItem(['item_id' => 1, 'qty_ordered' => 3, 'price_incl_tax' => 10.00,
            'discount_amount' => 10.00, 'tax_percent' => '21.0000', 'row_total' => 24.79]);
        $mug = $this->orderItem(['item_id' => 3, 'qty_ordered' => 3, 'price_incl_tax' => 20.00,
            'discount_amount' => 10.00, 'tax_percent' => 21.0, 'row_total' => 49.59]);
        $book = $this->orderItem(['item_id' => 4, 'qty_ordered' => 1, 'price_incl_tax' => 10.90,
            'tax_percent' => 9.0, 'row_total' => 10.00]);

        $invoice = $this->invoice(
            ['entity_id' => 10, 'grand_total' => 88.85, 'shipping_incl_tax' => 5.45],
            [
                $this->invoiceItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => 3, 'row_total_incl_tax' => 20.00], $shirt),
                $this->invoiceItem(['name' => 'Mug', 'sku' => 'MUG', 'qty' => 3, 'row_total_incl_tax' => 50.00], $mug),
                $this->invoiceItem(
                    ['name' => 'Child', 'sku' => 'CHILD', 'qty' => 1, 'row_total_incl_tax' => 5.00, 'parent_item_id' => 99],
                    $shirt
                ),
                $this->invoiceItem(['name' => 'Book', 'sku' => 'BOOK', 'qty' => 1, 'row_total_incl_tax' => 10.90], $book),
            ]
        );

        $order = $this->order(
            ['discount_amount' => -20.00],
            [$cancelled, $shirt, $mug, $book],
            [$invoice],
            $canInvoice
        );

        return [$order, $invoice];
    }

    /**
     * @param string      $typeId
     * @param string|null $priceType
     *
     * @return Product
     */
    private function product(string $typeId, ?string $priceType)
    {
        $attribute = null;
        if ($priceType !== null) {
            $attribute = $this->createMock(AttributeValue::class);
            $attribute->method('getValue')->willReturn($priceType);
        }

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getCustomAttribute')->with('price_type')->willReturn($attribute);

        return $product;
    }
}
