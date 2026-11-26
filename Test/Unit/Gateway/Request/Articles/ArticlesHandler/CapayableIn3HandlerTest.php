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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\CapayableIn3Handler;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * In3 is charged the sum of the article lines, so those lines must add up to the order total.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class CapayableIn3HandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    use ArticlesHandlerFixtures {
        order as private fixtureOrder;
        line as private fixtureLine;
        payment as private fixturePayment;
    }

    protected $instanceClass = CapayableIn3Handler::class;

    /**
     * @param float $unitPrice
     * @param float $expected
     */
    #[DataProvider('unitPriceProvider')]
    public function testTheUnitPriceIsNotLoweredByFloatingPointError(float $unitPrice, float $expected): void
    {
        $instance = $this->buildInstance([]);

        $line = $instance->getArticleArrayLine('Product', 'sku', 1, $unitPrice);

        $this->assertSame($expected, $line['price']);
    }

    public static function unitPriceProvider(): array
    {
        return [
            'a whole cent price is kept'     => [19.99, 19.99],
            'another whole cent price'       => [4.35, 4.35],
            'a sub-cent price is rounded down' => [19.9899, 19.98],
            'four decimals below a cent are still rounded down' => [12.349996, 12.34],
            'floating point noise below a cent is ignored' => [12.3499999, 12.35],
        ];
    }

    /**
     * Unit prices are rounded down, so the lines can fall short of the total by more than the
     * rounding tolerance. The shortfall must be charged, on a line of its own.
     */
    public function testAShortfallIsChargedOnAnAdjustmentLine(): void
    {
        $instance = $this->buildInstance([
            $this->line('a', 10, 9.98),
        ]);

        $articles = $instance->getOrderArticlesData($this->order(100.00), $this->payment());

        $this->assertEqualsWithDelta(100.00, $this->sum($articles), 0.001);
        $last = end($articles['articles']);
        $this->assertSame(AbstractArticlesHandler::ADJUSTMENT_IDENTIFIER, $last['identifier']);
        $this->assertEqualsWithDelta(0.20, $last['price'], 0.001);
    }

    public function testLinesThatAddUpToTheTotalAreSentUnchanged(): void
    {
        $instance = $this->buildInstance([
            $this->line('a', 2, 25.00),
            $this->line('b', 1, 50.00),
        ]);

        $articles = $instance->getOrderArticlesData($this->order(100.00), $this->payment());

        $this->assertCount(2, $articles['articles']);
        $this->assertEqualsWithDelta(100.00, $this->sum($articles), 0.001);
    }

    /**
     * In3 accepts no negative prices, so lines above the total cannot be corrected and the
     * payment is refused.
     */
    public function testLinesAboveTheTotalAreRefused(): void
    {
        $instance = $this->buildInstance([
            $this->line('a', 1, 105.00),
        ]);

        $this->expectException(BuckarooException::class);

        $instance->getOrderArticlesData($this->order(100.00), $this->payment());
    }

    /**
     * An order with more lines than one request may carry is refused.
     */
    public function testAnOrderWithMoreLinesThanIn3AcceptsIsRefused(): void
    {
        $lines = [];
        for ($i = 0; $i < AbstractArticlesHandler::MAX_ARTICLE_COUNT + 1; $i++) {
            $lines[] = $this->line('sku-' . $i, 1, 1.00);
        }
        $instance = $this->buildInstance($lines);

        $this->expectException(BuckarooException::class);

        $instance->getOrderArticlesData($this->order(100.00), $this->payment());
    }

    public function testTheItemLinesAreNotCutOffAtTheArticleLimit(): void
    {
        $count = AbstractArticlesHandler::MAX_ARTICLE_COUNT + 1;

        $instance = $this->getFakeMock(CapayableIn3Handler::class)
            ->onlyMethods([
                'getQuote',
                'skipBundleProducts',
                'skipItem',
                'calculateProductPrice',
                'getIdentifier',
                'getItemTax',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\QuoteItemStub::class)
                ->onlyMethods(['getTotalQty', 'getDiscountAmount', 'getName'])
                ->disableOriginalConstructor()
                ->getMock();
            $item->method('getTotalQty')->willReturn(1);
            $item->method('getDiscountAmount')->willReturn(0.0);
            $item->method('getName')->willReturn('Product ' . $i);
            $items[] = $item;
        }

        $quote = $this->getFakeMock(\Magento\Quote\Model\Quote::class)
            ->onlyMethods(['getAllItems'])
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getAllItems')->willReturn($items);

        $instance->method('getQuote')->willReturn($quote);
        $instance->method('skipBundleProducts')->willReturn(false);
        $instance->method('skipItem')->willReturn(false);
        $instance->method('calculateProductPrice')->willReturn(1.0);
        $instance->method('getIdentifier')->willReturn('sku');
        $instance->method('getItemTax')->willReturn(21.0);

        $this->assertCount($count, $this->invoke('getItemsLinesWithDiscount', $instance));
    }

    public function testAPayRemainderIsLeftAlone(): void
    {
        $instance = $this->buildInstance([], true);

        $articles = $instance->getOrderArticlesData($this->order(100.00), $this->payment());

        $this->assertCount(1, $articles['articles']);
        $this->assertEqualsWithDelta(40.00, $this->sum($articles), 0.001);
    }

    public function testAnOrderSendsItsNettedItemsFeeShippingAndCredits(): void
    {
        $this->cart([
            $this->quoteItem(['name' => 'Child', 'sku' => 'CHILD', 'qty' => '1.0000', 'parent_item_id' => 90,
                'price_incl_tax' => 24.20, 'row_total_incl_tax' => 24.20, 'tax_percent' => '21.0000']),
            $this->quoteItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => '2.0000', 'price_incl_tax' => 24.20,
                'row_total_incl_tax' => 48.40, 'tax_percent' => '21.0000', 'discount_amount' => 4.84]),
            $this->quoteItem(['name' => 'Book', 'sku' => 'BOOK', 'qty' => '1.0000', 'price_incl_tax' => 10.90,
                'row_total_incl_tax' => 10.90, 'tax_percent' => '9.0000']),
        ]);
        // 2 x 21.78 + 10.90 + 2.50 + 5.45 - 5.00
        $order = $this->fixtureOrder(['grand_total' => 57.41, 'buckaroo_fee_incl_tax' => 2.50,
            'shipping_incl_tax' => 5.45, 'gift_cards_amount' => 5.00]);

        $this->assertSame(
            ['articles' => [
                ['identifier' => 'SHIRT', 'description' => 'Shirt', 'quantity' => '2.0000', 'price' => 21.78],
                ['identifier' => 'BOOK', 'description' => 'Book', 'quantity' => '1.0000', 'price' => 10.9],
                ['identifier' => 1, 'description' => 'Service Costs', 'quantity' => 1, 'price' => 2.5],
                ['identifier' => 2, 'description' => 'Shipping fee', 'quantity' => 1, 'price' => 5.45],
                ['identifier' => 6, 'description' => 'Discount Gift Card', 'quantity' => 1, 'price' => -5.0],
            ]],
            $this->in3()->getOrderArticlesData($order, $this->fixturePayment())
        );
    }

    public function testAShortfallBeyondRoundingIsChargedInFull(): void
    {
        $this->cart([
            $this->quoteItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => 1, 'price_incl_tax' => 9.74,
                'row_total_incl_tax' => 9.74, 'tax_percent' => '21.0000']),
        ]);

        $this->assertSame(
            ['articles' => [
                ['identifier' => 'SHIRT', 'description' => 'Shirt', 'quantity' => 1, 'price' => 9.74],
                ['identifier' => 'adjustment', 'description' => 'Adjustment', 'quantity' => 1, 'price' => 0.26],
            ]],
            $this->in3()->getOrderArticlesData($this->fixtureOrder(['grand_total' => 10.00]), $this->fixturePayment())
        );
    }

    public function testAFullOrderWithinOneCentIsAccepted(): void
    {
        $items = [];
        for ($i = 1; $i <= AbstractArticlesHandler::MAX_ARTICLE_COUNT; $i++) {
            $items[] = $this->quoteItem(['name' => 'P' . $i, 'sku' => 'P' . $i, 'qty' => 1,
                'price_incl_tax' => 1.00, 'row_total_incl_tax' => 1.00, 'tax_percent' => '21.0000']);
        }
        $this->cart($items);

        $articles = $this->in3()->getOrderArticlesData(
            $this->fixtureOrder(['grand_total' => 99.01]),
            $this->fixturePayment()
        );

        $this->assertCount(AbstractArticlesHandler::MAX_ARTICLE_COUNT, $articles['articles']);
    }

    public function testAPayRemainderIsOneLineForTheRemainingAmount(): void
    {
        $this->isPayRemainder = true;
        $this->payRemainder = 12.344;

        $this->assertSame(
            ['articles' => [0 => ['identifier' => 1, 'description' => 'PayRemainder', 'quantity' => 1, 'price' => 12.34]]],
            $this->in3()->getOrderArticlesData($this->fixtureOrder([]), $this->fixturePayment())
        );
    }

    /**
     * @return CapayableIn3Handler
     */
    private function in3(): CapayableIn3Handler
    {
        return new CapayableIn3Handler(...$this->handlerArgs());
    }

    /**
     * @param array $itemLines
     * @param bool  $isPayRemainder
     *
     * @return CapayableIn3Handler
     */
    private function buildInstance(array $itemLines, bool $isPayRemainder = false)
    {
        $instance = $this->getFakeMock(CapayableIn3Handler::class)
            ->onlyMethods([
                'getItemsLinesWithDiscount',
                'getServiceCostLine',
                'getShippingCostsLine',
                'getAdditionalLines',
                'getRequestArticlesDataPayRemainder',
                'getAdjustmentLabel',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $instance->method('getItemsLinesWithDiscount')->willReturn($itemLines);
        $instance->method('getServiceCostLine')->willReturn([]);
        $instance->method('getShippingCostsLine')->willReturn([]);
        $instance->method('getAdditionalLines')->willReturn([]);
        $instance->method('getRequestArticlesDataPayRemainder')->willReturn($this->line('PayRemainder', 1, 40.00));
        $instance->method('getAdjustmentLabel')->willReturn(__('Adjustment'));

        $payReminderService = $this->getFakeMock(\Buckaroo\Magento2\Service\PayReminderService::class)->getMock();
        $payReminderService->method('isPayRemainder')->willReturn($isPayRemainder);

        $this->setProperty('payReminderService', $payReminderService, $instance);
        $this->setProperty(
            'buckarooLog',
            $this->getFakeMock(\Buckaroo\Magento2\Logging\BuckarooLoggerInterface::class)->getMock(),
            $instance
        );

        return $instance;
    }

    private function line(string $identifier, int $quantity, float $price): array
    {
        return [
            'identifier' => $identifier,
            'description' => $identifier,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }

    private function order(float $grandTotal)
    {
        $order = $this->getFakeMock(\Magento\Sales\Model\Order::class)->getMock();
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getIncrementId')->willReturn('000000165');

        return $order;
    }

    private function payment()
    {
        return $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
    }

    private function sum(array $articles): float
    {
        $sum = 0.0;
        foreach ($articles['articles'] as $article) {
            $sum += $article['price'] * $article['quantity'];
        }

        return round($sum, 2);
    }
}
