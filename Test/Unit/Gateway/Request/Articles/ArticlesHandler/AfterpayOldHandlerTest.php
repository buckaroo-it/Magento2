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

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AfterpayOldHandler;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticleTotalRegistry;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountDebitDataBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The legacy AfterPay request carries shipping in its own ShippingCosts field, next to the articles.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class AfterpayOldHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    use ArticlesHandlerFixtures {
        order as private fixtureOrder;
        line as private fixtureLine;
        payment as private fixturePayment;
    }

    protected $instanceClass = AfterpayOldHandler::class;

    public function testShippingSentAsShippingCostsIsNotAddedAgainAsAnArticle(): void
    {
        $instance = $this->buildInstance([$this->line('1', 1, 19.99)], 4.95);

        $articles = $instance->getOrderArticlesData($this->order(24.94, 4.33), $this->payment());

        $this->assertSame(4.95, $articles['shippingCosts']);
        $this->assertSame(['1'], array_column($articles['articles'], 'identifier'));
        $this->assertEqualsWithDelta(
            24.94,
            $articles['articles'][0]['price'] * $articles['articles'][0]['quantity'] + $articles['shippingCosts'],
            0.001
        );
    }

    public function testTheAmountDebitStillCoversTheShippingCosts(): void
    {
        $instance = $this->buildInstance([$this->line('1', 1, 19.99)], 4.95);
        $order = $this->order(24.94, 4.33);
        $order->method('getIncrementId')->willReturn('000000165');

        $articles = $instance->getOrderArticlesData($order, $this->payment());

        $registry = new ArticleTotalRegistry();
        $registry->set(ArticleTotalRegistry::CONTEXT_ORDER, '000000165', $registry->sumArticles($articles));

        $this->assertSame(24.94, (new AmountDebitDataBuilder($registry))->getAmount($order));
    }

    public function testAnUnaccountedAmountIsSentWithAnAfterpayVatCategory(): void
    {
        $instance = $this->buildInstance([$this->line('1', 1, 19.99)], 4.95);

        $articles = $instance->getOrderArticlesData($this->order(26.94, 0.35), $this->payment());

        $this->assertSame(['1', 'extra-fees'], array_column($articles['articles'], 'identifier'));
        $extraFees = end($articles['articles']);
        $this->assertEqualsWithDelta(2.00, $extraFees['price'], 0.001);
        $this->assertSame(AfterpayOldHandler::DEFAULT_TAX_CATEGORY, $extraFees['vatCategory']);
    }

    public function testArticlesThatAddUpWithoutShippingAreSentUnchanged(): void
    {
        $instance = $this->buildInstance([$this->line('1', 2, 10.00)], 0.0);

        $articles = $instance->getOrderArticlesData($this->order(20.00, 3.47), $this->payment());

        $this->assertArrayNotHasKey('shippingCosts', $articles);
        $this->assertSame(['1'], array_column($articles['articles'], 'identifier'));
    }

    public function testADiscountedShippingIsSentAsTheAmountPaid(): void
    {
        // 5.00 shipping + 21% tax, 10% (0.50) shipping discount: 4.50 + 0.95 tax paid.
        $instance = $this->buildInstance([$this->line('1', 1, 30.60)], 6.05);
        $this->setProperty('scopeConfig', $this->scopeConfig(true), $instance);
        $order = $this->order(36.05, 6.26);
        $order->method('getIncrementId')->willReturn('000000616');
        $this->withShipping($order, 5.00, 0.95, 0.50);

        $articles = $instance->getOrderArticlesData($order, $this->payment());

        $this->assertSame(5.45, $articles['shippingCosts']);
        $this->assertSame(['1'], array_column($articles['articles'], 'identifier'));

        $registry = new ArticleTotalRegistry();
        $registry->set(ArticleTotalRegistry::CONTEXT_ORDER, '000000616', $registry->sumArticles($articles));
        $this->assertSame(36.05, (new AmountDebitDataBuilder($registry))->getAmount($order));
    }

    public function testDiscountLinesAreSentWithTheItemTaxCategory(): void
    {
        $instance = $this->getFakeMock(AfterpayOldHandler::class)
            ->onlyMethods([
                'getDiscountAmount',
                'getStoreCreditAmount',
                'getOrderVatGroups',
                'getTaxCategory',
                'getOrder',
                'renderInOrderStoreLocale',
            ])
            ->getMock();
        $instance->method('getDiscountAmount')->willReturn(-10.00);
        $instance->method('getStoreCreditAmount')->willReturn(4.00);
        $instance->method('getOrderVatGroups')->willReturn([
            '21' => ['rowTotal' => 60.00],
            '9' => ['rowTotal' => 40.00],
        ]);
        $instance->method('getTaxCategory')->willReturn(AfterpayOldHandler::HIGH_TAX_CATEGORY);
        $instance->method('getOrder')->willReturn($this->order(100.00, 0.0));
        $instance->method('renderInOrderStoreLocale')->willReturnCallback(fn(callable $render) => (string)$render());

        $discountLines = $instance->getDiscountLines();
        $storeCreditLines = $this->invoke('getStoreCreditLines', $instance);

        $this->assertCount(2, $discountLines);
        $this->assertCount(2, $storeCreditLines);
        $this->assertSame([-6.0, -4.0], array_column($discountLines, 'price'));
        foreach (array_merge($discountLines, $storeCreditLines) as $line) {
            $this->assertSame(AfterpayOldHandler::HIGH_TAX_CATEGORY, $line['vatCategory']);
        }
    }

    public function testTheCapturedTotalARefundIsCappedAtIncludesTheShippingCosts(): void
    {
        $instance = $this->buildInstance([], 0.0);

        $total = $this->invokeArgs(
            'sumArticleLines',
            [['articles' => [$this->line('1', 1, 30.60)], 'shippingCosts' => 5.45]],
            $instance
        );

        $this->assertSame(36.05, $total);
    }

    #[DataProvider('refundedShippingProvider')]
    public function testACreditMemoRefundsItsShareOfTheDiscountedShipping(
        float $refundedShipping,
        float $refundedShippingTax,
        float $expected
    ): void {
        $instance = $this->buildInstance([], 6.05);
        $this->setProperty('scopeConfig', $this->scopeConfig(true), $instance);
        $order = $this->order(36.05, 6.26);
        $this->withShipping($order, 5.00, 0.95, 0.50);
        $instance->setOrder($order);

        $creditmemo = $this->getFakeMock(\Magento\Sales\Model\Order\Creditmemo::class)->getMock();
        $creditmemo->method('getShippingAmount')->willReturn($refundedShipping);
        $creditmemo->method('getShippingTaxAmount')->willReturn($refundedShippingTax);

        $itemsTotalAmount = 0.0;
        $line = (new \ReflectionMethod($instance, 'getShippingCostsLine'))
            ->invokeArgs($instance, [$creditmemo, &$itemsTotalAmount, true]);

        $this->assertSame($expected, $line['shippingCosts'] ?? 0.0);
    }

    public static function refundedShippingProvider(): array
    {
        return [
            'all shipping' => [5.00, 0.95, 5.45],
            'half the shipping' => [2.50, 0.48, 2.73],
            'no shipping' => [0.0, 0.0, 0.0],
        ];
    }

    /**
     * @param float      $shipping
     * @param float      $grandTotal
     * @param array      $tail
     * @param float|null $shippingCosts
     */
    #[DataProvider('orderProvider')]
    public function testAnOrderSendsTaxCategoriesAndShippingCostsBesideTheArticles(
        float $shipping,
        float $grandTotal,
        array $tail,
        ?float $shippingCosts
    ): void {
        $this->cart([
            $this->quoteItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'product_id' => 42, 'qty' => '2.0000',
                'price_incl_tax' => 24.20, 'row_total_incl_tax' => 48.40, 'tax_percent' => '21.0000',
                'discount_amount' => 4.84]),
        ]);
        $order = $this->fixtureOrder([
            'grand_total' => $grandTotal, 'tax_amount' => 0.0, 'shipping_incl_tax' => $shipping,
            'buckaroo_fee_incl_tax' => 2.50, 'reward_currency_amount' => 1.00,
        ]);

        $expected = ['articles' => array_merge([
            $this->categoryLine('42', 'Shirt', 1.0, 2, 21.78),
            $this->categoryLine('1', 'Service Costs', 1, 1, 2.5),
            $this->categoryLine('5', 'Discount Reward Points', 4, 1, -1.0),
        ], $tail)];
        if ($shippingCosts !== null) {
            $expected['shippingCosts'] = $shippingCosts;
        }

        $this->assertSame(
            $expected,
            $this->afterpayOld()->getOrderArticlesData($order, $this->fixturePayment())
        );
    }

    public static function orderProvider(): array
    {
        $adjustment = ['identifier' => 'adjustment', 'description' => 'Adjustment', 'vatCategory' => 4,
            'quantity' => 1, 'price' => 0.03];

        // the lines add up to 43.56 + 2.50 - 1.00 = 45.06
        return [
            'rounding beside the shipping costs' => [5.45, 50.54, [$adjustment], 5.45],
            'rounding without shipping' => [0.0, 45.09, [$adjustment], null],
            'an unaccounted amount beside the shipping costs' => [5.45, 51.51, [[
                'identifier' => 'extra-fees', 'description' => 'Extra Fees', 'vatCategory' => 4,
                'quantity' => 1, 'price' => 1.0,
            ]], 5.45],
        ];
    }

    /**
     * @param int        $qty
     * @param float      $price
     * @param float      $discount
     * @param bool       $canInvoice
     * @param float      $expectedPrice
     * @param float|null $adjustment
     */
    #[DataProvider('captureProvider')]
    public function testACaptureKeepsItsShippingCosts(
        int $qty,
        float $price,
        float $discount,
        bool $canInvoice,
        float $expectedPrice,
        ?float $adjustment
    ): void {
        $orderItem = $this->orderItem(['item_id' => 1, 'qty_ordered' => $qty, 'price_incl_tax' => $price,
            'discount_amount' => $discount, 'tax_percent' => 21.0, 'row_total' => 10.0]);
        $invoice = $this->invoice(
            ['entity_id' => 10, 'shipping_incl_tax' => 5.45],
            [$this->invoiceItem(['name' => 'Shirt', 'product_id' => 42, 'qty' => $qty,
                'row_total_incl_tax' => 10.0], $orderItem)]
        );
        $order = $this->fixtureOrder(['discount_amount' => -$discount], [$orderItem], [$invoice], $canInvoice);

        $lines = [$this->categoryLine('42', 'Shirt', 1.0, $qty, $expectedPrice)];
        if ($adjustment !== null) {
            $lines[] = $this->categoryLine('adjustment', 'Adjustment', 4, 1, $adjustment);
        }

        $this->assertSame(
            ['articles' => $lines, 'shippingCosts' => 5.45],
            $this->afterpayOld()->getInvoiceArticlesData($order, $this->fixturePayment())
        );
    }

    public static function captureProvider(): array
    {
        return [
            'an intermediate capture' => [2, 24.20, 4.84, true, 21.78, null],
            'the closing capture without rounding' => [2, 24.20, 4.84, false, 21.78, null],
            'the closing capture with rounding' => [3, 10.00, 10.00, false, 6.67, -0.01],
        ];
    }

    /**
     * @param float      $grandTotal
     * @param float|null $diff
     */
    #[DataProvider('refundProvider')]
    public function testARefundKeepsItsShippingCostsAndCategories(float $grandTotal, ?float $diff): void
    {
        $orderItem = $this->orderItem(['tax_percent' => 21.0]);
        $creditmemo = $this->creditmemo(
            ['grand_total' => $grandTotal, 'buckaroo_fee_incl_tax' => 2.50, 'shipping_incl_tax' => 5.45],
            [$this->creditmemoItem(['name' => 'Shirt', 'product_id' => 42, 'qty' => 1, 'price_incl_tax' => 24.20,
                'row_total_incl_tax' => 24.20, 'discount_amount' => 0.0], $orderItem)]
        );
        $order = $this->fixtureOrder([]);

        $lines = [
            $this->categoryLine('42', 'Shirt', 1.0, 1, 24.2),
            $this->categoryLine('1', 'Service Costs', 1, 1, 2.5) + ['refundType' => 'Refund'],
        ];
        $expected = ['articles' => $lines, 'shippingCosts' => 5.45];
        if ($diff !== null) {
            $expected['articles'][] = $this->categoryLine('4', 'Discount/Fee', 4, 1, $diff) + ['refundType' => 'Refund'];
        }

        $this->assertSame(
            $expected,
            $this->afterpayOld()->getCreditMemoArticlesData($order, $this->fixturePayment($order, $creditmemo))
        );
    }

    public static function refundProvider(): array
    {
        // 24.20 + 2.50 + 5.45 shipping = 32.15
        return [
            'lines and shipping add up' => [32.15, null],
            'a difference is refunded on its own line' => [32.25, 0.1],
        ];
    }

    /**
     * @param array $orderData
     * @param array $creditmemoData
     * @param float $expected
     */
    #[DataProvider('refundedShippingShareProvider')]
    public function testTheRefundedShippingShare(array $orderData, array $creditmemoData, float $expected): void
    {
        $handler = $this->afterpayOld();
        $handler->setOrder($this->fixtureOrder($orderData));

        $this->assertSame(
            $expected,
            $this->invokeArgs('getRefundedShippingAmount', [$this->creditmemo($creditmemoData, [])], $handler)
        );
    }

    public static function refundedShippingShareProvider(): array
    {
        return [
            'a one cent discount' => [
                ['shipping_amount' => 10.00, 'shipping_discount_amount' => -0.01],
                ['shipping_amount' => 10.00, 'shipping_tax_amount' => 2.10, 'shipping_incl_tax' => 12.10],
                12.09,
            ],
            'no discount refunds the shipping incl tax' => [
                ['shipping_amount' => 10.00],
                ['shipping_amount' => 10.00, 'shipping_tax_amount' => 2.00, 'shipping_incl_tax' => 12.10],
                12.10,
            ],
            'an order without shipping' => [
                ['shipping_amount' => 0.0, 'shipping_discount_amount' => -5.00],
                ['shipping_amount' => 0.0, 'shipping_incl_tax' => 0.0],
                0.0,
            ],
            'half the shipping with tax compensation' => [
                ['shipping_amount' => 10.00, 'shipping_discount_amount' => -5.00],
                ['shipping_amount' => 5.00, 'shipping_tax_amount' => 0.50,
                    'shipping_discount_tax_compensation_amount' => 0.10],
                3.1,
            ],
        ];
    }

    public function testTheArticleLineCarriesATaxCategory(): void
    {
        $handler = $this->afterpayOld();

        $line = ['identifier' => '42', 'description' => 'Thing', 'vatCategory' => 2, 'quantity' => 3, 'price' => 1.01];
        $this->assertSame($line, $handler->getArticleArrayLine('Thing', 42, 2.5, 1.006, 2));
        $this->assertSame($line, $handler->getArticleRefundArrayLine('Thing', 42, 2.5, 1.006, 2));

        $line = ['identifier' => '7', 'description' => null, 'vatCategory' => 4, 'quantity' => 2, 'price' => 1.0];
        $this->assertSame($line, $handler->getArticleArrayLine(null, 7, 2.4, 0.996, ''));
        $this->assertSame($line, $handler->getArticleRefundArrayLine(null, 7, 2.4, 0.996, ''));
    }

    /**
     * @return AfterpayOldHandler
     */
    private function afterpayOld(): AfterpayOldHandler
    {
        $handler = new AfterpayOldHandler(...$this->handlerArgs());

        $config = $this->createMock(\Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay::class);
        $config->method('getHighTaxClasses')->willReturn('7');
        $this->methodFactory->method('get')->willReturn($config);

        return $handler;
    }

    /**
     * @param string $identifier
     * @param string $description
     * @param int|float $category
     * @param int    $quantity
     * @param float  $price
     *
     * @return array
     */
    private function categoryLine(string $identifier, string $description, $category, int $quantity, float $price): array
    {
        return [
            'identifier' => $identifier,
            'description' => $description,
            'vatCategory' => $category,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }

    /**
     * @param array $itemLines
     * @param float $shippingAmount
     *
     * @return AfterpayOldHandler
     */
    private function buildInstance(array $itemLines, float $shippingAmount)
    {
        $instance = $this->getFakeMock(AfterpayOldHandler::class)
            ->onlyMethods([
                'getItemsLines',
                'getServiceCostLine',
                'getShippingAmount',
                'getDiscountLines',
                'getAdditionalLines',
                'renderInOrderStoreLocale',
            ])
            ->getMock();

        $instance->method('getItemsLines')->willReturn($itemLines);
        $instance->method('getServiceCostLine')->willReturn([]);
        $instance->method('getShippingAmount')->willReturn($shippingAmount);
        $instance->method('getDiscountLines')->willReturn([]);
        $instance->method('getAdditionalLines')->willReturn([]);
        $instance->method('renderInOrderStoreLocale')->willReturnCallback(fn(callable $render) => (string)$render());

        $payReminderService = $this->getFakeMock(\Buckaroo\Magento2\Service\PayReminderService::class)->getMock();
        $payReminderService->method('isPayRemainder')->willReturn(false);

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
            'vatCategory' => AfterpayOldHandler::DEFAULT_TAX_CATEGORY,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }

    private function order(float $grandTotal, float $taxAmount)
    {
        $order = $this->getFakeMock(\Magento\Sales\Model\Order::class)->getMock();
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getTaxAmount')->willReturn($taxAmount);

        return $order;
    }

    private function withShipping($order, float $shipping, float $shippingTax, float $shippingDiscount): void
    {
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingAmount')->willReturn($shipping);
        $order->method('getShippingTaxAmount')->willReturn($shippingTax);
        $order->method('getShippingDiscountAmount')->willReturn($shippingDiscount);
    }

    private function scopeConfig(bool $pricesIncludeTax)
    {
        $scopeConfig = $this->getFakeMock(\Magento\Framework\App\Config\ScopeConfigInterface::class)->getMock();
        $scopeConfig->method('getValue')->willReturn($pricesIncludeTax ? '1' : '0');

        return $scopeConfig;
    }

    private function payment()
    {
        return $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
    }
}
