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

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

/**
 * The lines a credit memo refunds carry the article numbers and unit prices the reserve sent.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaKpCreditmemoRefundLinesTest extends TestCase
{
    public function testRefundedItemsCarryTheirReservedNumberQuantityAndPrice(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36), $this->orderItem(12, '24-MB01', 34.00)];
        $creditmemoItems = [
            $this->creditmemoItem($orderItems[0], 2),
            // Not refunded: Magento still adds the line, with no quantity
            $this->creditmemoItem($orderItems[1], 0),
        ];

        $this->assertSame(
            [['identifier' => '24-WB01', 'quantity' => 2, 'price' => 19.36]],
            $this->linesFor($orderItems, $creditmemoItems, false)
        );
    }

    public function testLinesThatShareASkuUseTheUniqueReservedNumber(): void
    {
        $orderItems = [$this->orderItem(11, 'SH01', 10.00), $this->orderItem(12, 'SH01', 12.00)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[1], 1)];

        $this->assertSame(
            [['identifier' => 'SH01-12', 'quantity' => 1, 'price' => 12.00]],
            $this->linesFor($orderItems, $creditmemoItems, true)
        );
    }

    public function testAFractionalQuantityCannotBeNominated(): void
    {
        $orderItems = [$this->orderItem(11, 'CHEESE', 4.00)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1.5)];

        $this->assertSame([], $this->linesFor($orderItems, $creditmemoItems, false));
    }

    public function testAPartialPaymentIsNotNominated(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1)];

        $this->assertSame([], $this->linesFor($orderItems, $creditmemoItems, false, true));
    }

    /**
     * @param array $orderItems
     * @param array $creditmemoItems
     * @param bool  $uniqueArticleNumbers
     * @param bool  $payRemainder
     *
     * @return array
     */
    private function linesFor(
        array $orderItems,
        array $creditmemoItems,
        bool $uniqueArticleNumbers,
        bool $payRemainder = false
    ): array {
        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn($orderItems);

        $creditmemo = $this->getMockBuilder(Order\Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getShippingAmount'])
            ->getMock();
        $creditmemo->method('getAllItems')->willReturn($creditmemoItems);
        $creditmemo->method('getShippingAmount')->willReturn(0.0);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation', 'getCreditmemo'])
            ->getMock();
        $payment->method('getCreditmemo')->willReturn($creditmemo);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn($key = null) => $key === KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS ? $uniqueArticleNumbers : null
        );

        $payReminderService = $this->createMock(PayReminderService::class);
        $payReminderService->method('isPayRemainder')->willReturn($payRemainder);

        $handler = new KlarnaKpHandler(
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(QuoteFactory::class),
            $this->createMock(Calculation::class),
            $this->createMock(TaxConfig::class),
            $this->createMock(BuckarooFee::class),
            $this->createMock(SoftwareData::class),
            $this->createMock(ConfigProviderMethodFactory::class),
            $payReminderService,
            $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class)
        );

        return $handler->getCreditmemoRefundLines($order, $payment);
    }

    /**
     * @param int    $quoteItemId
     * @param string $sku
     * @param float  $priceInclTax
     *
     * @return Order\Item
     */
    private function orderItem(int $quoteItemId, string $sku, float $priceInclTax)
    {
        $item = $this->getMockBuilder(Order\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuoteItemId', 'getSku', 'getParentItemId', 'getParentItem', 'getPriceInclTax'])
            ->getMock();
        $item->method('getQuoteItemId')->willReturn($quoteItemId);
        $item->method('getSku')->willReturn($sku);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getParentItem')->willReturn(null);
        $item->method('getPriceInclTax')->willReturn($priceInclTax);

        return $item;
    }

    /**
     * @param Order\Item $orderItem
     * @param float      $qty
     *
     * @return Order\Creditmemo\Item
     */
    private function creditmemoItem($orderItem, float $qty)
    {
        $item = $this->getMockBuilder(Order\Creditmemo\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrderItem', 'getSku', 'getQty', 'getRowTotalInclTax'])
            ->getMock();
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getSku')->willReturn($orderItem->getSku());
        $item->method('getQty')->willReturn($qty);
        $item->method('getRowTotalInclTax')->willReturn($qty * (float)$orderItem->getPriceInclTax());

        return $item;
    }
}
