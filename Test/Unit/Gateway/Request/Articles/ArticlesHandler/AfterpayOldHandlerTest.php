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

    private function payment()
    {
        return $this->getFakeMock(\Magento\Sales\Model\Order\Payment::class)->getMock();
    }
}
