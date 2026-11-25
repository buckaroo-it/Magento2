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
 */
class AfterpayOldHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
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
