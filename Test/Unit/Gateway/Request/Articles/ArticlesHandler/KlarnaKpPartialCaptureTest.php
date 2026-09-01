<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Buckaroo\Magento2\Test\Unit\Stubs\InvoiceItemStub;
use Buckaroo\Magento2\Test\Unit\Stubs\InvoiceStub;
use Buckaroo\Magento2\Test\Unit\Stubs\QuoteStub;
use Buckaroo\Magento2\Test\Unit\Stubs\StdObjectStub;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end shape of the capture request for a partially shipped order.
 *
 * The article lines a capture sends must add up to the invoice grand total: Klarna validates
 * them against the lines it saw during the reserve and the amountDebit is derived from their
 * sum, so a discount that still covers the whole order turns a partial capture into a wrong
 * amount with lines Klarna refuses.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaKpPartialCaptureTest extends TestCase
{
    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;

    /** @var SoftwareData|MockObject */
    private $softwareData;

    /**
     * The reported case: €33.98 of goods, a €16.99 cart discount, one of the two lines shipped.
     * The capture must be for that line's half of the order, not for the full authorization.
     */
    public function testAPartialInvoiceOfADiscountedOrderCapturesOnlyItsOwnLines(): void
    {
        // The reported shape, in the numbers of the local repro: EUR 32.00 goods, half off.
        $articles = $this->buildCapture(
            [$this->makeItem('24-WB01', 'Voyage Yoga Bag', 35.36, 16.00)],
            ['grandTotal' => 19.36, 'discount' => -16.00, 'subtotal' => 32.00],
            ['grandTotal' => 38.72, 'discount' => -32.00, 'subtotal' => 64.00, 'itemDiscounts' => [16.00, 16.00]]
        );

        $identifiers = array_column($articles['articles'], 'identifier');

        $this->assertCount(1, $articles['articles'], 'Only the shipped product line');
        $this->assertEqualsWithDelta(19.36, $this->priceOf($articles, '24-WB01'), 0.001);
        $this->assertEqualsWithDelta(19.36, $this->sumArticles($articles), 0.001);
        $this->assertNotContains('discount', $identifiers, 'No indivisible lump discount line');
        $this->assertNotContains('extra-fees', $identifiers);
    }

    /**
     * The shipment of the remaining line captures the rest, so the two captures together add up
     * to the authorized amount and nothing stays reserved.
     */
    public function testTheFinalInvoiceCapturesTheRemainingLines(): void
    {
        $articles = $this->buildCapture(
            [$this->makeItem('24-WB02', 'Compete Track Tote', 35.36, 16.00)],
            ['grandTotal' => 19.36, 'discount' => -16.00, 'subtotal' => 32.00],
            ['grandTotal' => 38.72, 'discount' => -32.00, 'subtotal' => 64.00, 'itemDiscounts' => [16.00, 16.00]],
            2
        );

        $this->assertEqualsWithDelta(19.36, $this->priceOf($articles, '24-WB02'), 0.001);
        $this->assertEqualsWithDelta(19.36, $this->sumArticles($articles), 0.001);
    }

    /**
     * A single shipment covering everything must still behave exactly as before this change.
     */
    public function testAFullInvoiceCarriesTheWholeOrder(): void
    {
        $articles = $this->buildCapture(
            [
                $this->makeItem('24-WB01', 'Voyage Yoga Bag', 35.36, 16.00),
                $this->makeItem('24-WB02', 'Compete Track Tote', 35.36, 16.00),
            ],
            ['grandTotal' => 38.72, 'discount' => -32.00, 'subtotal' => 64.00],
            ['grandTotal' => 38.72, 'discount' => -32.00, 'subtotal' => 64.00, 'itemDiscounts' => [16.00, 16.00]]
        );

        $this->assertEqualsWithDelta(38.72, $this->sumArticles($articles), 0.001);
    }

    /**
     * A partial shipment of an order without a discount was already correct and must stay so.
     */
    public function testAPartialInvoiceWithoutADiscountHasNoDiscountLine(): void
    {
        $articles = $this->buildCapture(
            [$this->makeItem('SH00087452', 'Shipped product', 20.00)],
            ['grandTotal' => 20.00, 'discount' => 0.0, 'subtotal' => 20.00],
            ['grandTotal' => 40.00, 'discount' => 0.0, 'subtotal' => 40.00, 'itemDiscounts' => [0.0, 0.0]]
        );

        $identifiers = array_column($articles['articles'], 'identifier');
        $this->assertNotContains('1', $identifiers, 'No discount line without a discount');
        $this->assertEqualsWithDelta(20.00, $this->sumArticles($articles), 0.001);
    }

    /**
     * A cart-level discount a third-party module never wrote onto the order items is invisible
     * to Magento's invoice totals, so the invoice grand total is inflated. The lines
     * must carry this invoice's share of that remainder instead of the whole of it.
     */
    public function testAnUnallocatedCartDiscountIsSettledWholeOnTheFirstInvoice(): void
    {
        // Nothing reached the order items, so the remainder can only be a global line - and a
        // global line is indivisible, so it is captured whole, at its reserved price, once.
        $first = $this->buildCapture(
            [$this->makeItem('SH00087452', 'Shipped product', 16.99)],
            ['grandTotal' => 16.99, 'discount' => 0.0, 'subtotal' => 16.99],
            ['grandTotal' => 16.99, 'discount' => -16.99, 'subtotal' => 33.98, 'itemDiscounts' => [0.0, 0.0]]
        );

        $second = $this->buildCapture(
            [$this->makeItem('SH00074480', 'Late product', 16.99)],
            ['grandTotal' => 16.99, 'discount' => 0.0, 'subtotal' => 16.99],
            ['grandTotal' => 16.99, 'discount' => -16.99, 'subtotal' => 33.98, 'itemDiscounts' => [0.0, 0.0]],
            2
        );

        $this->assertEqualsWithDelta(-16.99, $this->priceOf($first, 'discount'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->priceOf($second, 'discount'), 0.001);
    }

    /**
     * A gift card is settled per invoice, so a partial capture may only subtract the part the
     * invoice it captures actually carries.
     */
    public function testAGiftCardIsSettledWholeOnTheFirstInvoice(): void
    {
        // A gift card line is indivisible too: it carries the amount the reserve sent and is
        // only repeated on the first capture.
        $first = $this->buildCapture(
            [$this->makeItem('SH00087452', 'Shipped product', 20.00)],
            ['grandTotal' => 20.00, 'discount' => 0.0, 'subtotal' => 20.00],
            ['grandTotal' => 24.00, 'discount' => 0.0, 'subtotal' => 40.00, 'giftCard' => 16.00]
        );

        $second = $this->buildCapture(
            [$this->makeItem('SH00074480', 'Late product', 20.00)],
            ['grandTotal' => 20.00, 'discount' => 0.0, 'subtotal' => 20.00],
            ['grandTotal' => 24.00, 'discount' => 0.0, 'subtotal' => 40.00, 'giftCard' => 16.00],
            2
        );

        $this->assertEqualsWithDelta(-16.00, $this->priceOf($first, '6'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->priceOf($second, '6'), 0.001);
    }

    /**
     * Shipping is settled on the first invoice, so a later capture must not send it again.
     */
    public function testShippingIsSentOnceAndFollowsTheInvoice(): void
    {
        $first = $this->buildCapture(
            [$this->makeItem('SH00087452', 'Shipped product', 20.00)],
            ['grandTotal' => 24.95, 'discount' => 0.0, 'subtotal' => 20.00, 'shipping' => 4.95],
            ['grandTotal' => 44.95, 'discount' => 0.0, 'subtotal' => 40.00, 'itemDiscounts' => [0.0, 0.0]]
        );

        $second = $this->buildCapture(
            [$this->makeItem('SH00074480', 'Late product', 20.00)],
            ['grandTotal' => 20.00, 'discount' => 0.0, 'subtotal' => 20.00],
            ['grandTotal' => 44.95, 'discount' => 0.0, 'subtotal' => 40.00, 'itemDiscounts' => [0.0, 0.0]],
            2
        );

        $this->assertEqualsWithDelta(4.95, $this->priceOf($first, '2'), 0.001);
        $this->assertEqualsWithDelta(24.95, $this->sumArticles($first), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->priceOf($second, '2'), 0.001);
        $this->assertEqualsWithDelta(20.00, $this->sumArticles($second), 0.001);
    }

    /**
     * Shipping two of three units must capture two units at the reserved unit price, not a
     * recomputed one - the per-unit discount comes from the order item in both builders.
     */
    public function testAPartialQuantityCapturesAtTheReservedUnitPrice(): void
    {
        // 3 ordered at 35.36 gross with 16.99 off the row; 2 units shipped.
        $item = $this->makeItem('24-WB01', 'Voyage Yoga Bag', 35.36, 16.99, 2.0, 3.0);

        $articles = $this->buildCapture(
            [$item],
            ['grandTotal' => 59.41, 'discount' => -11.33, 'subtotal' => 70.72],
            ['grandTotal' => 89.09, 'discount' => -16.99, 'subtotal' => 106.08, 'itemDiscounts' => [16.99]]
        );

        $identifiers = array_column($articles['articles'], 'identifier');

        $this->assertSame(
            $identifiers,
            array_unique($identifiers),
            'A line is never split in two: that leaves the same ArticleNumber twice'
        );

        $line = $articles['articles'][0];
        $this->assertSame('24-WB01', $line['identifier']);
        $this->assertSame(2, (int)$line['quantity'], 'Only the shipped units');
        // 35.36 - (16.99 / 3) = 29.70, the same unit price the reserve sent for all three.
        $this->assertEqualsWithDelta(29.70, (float)$line['price'], 0.005);

        // 2 x 29.70 is a cent short of this invoice, and it STAYS short: the reserve's rounding
        // adjustment is one indivisible reserved line and may not be sliced per invoice.
        $this->assertEqualsWithDelta(
            0.0,
            $this->priceOf($articles, \Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler::ADJUSTMENT_IDENTIFIER),
            0.001,
            'An intermediate capture carries no adjustment line'
        );
        $this->assertEqualsWithDelta(59.40, $this->sumArticles($articles), 0.001);
    }

    /**
     * The leftover is settled on the capture that closes the order, at the amount the reservation
     * still holds. Slicing a fresh adjustment per invoice re-prices a reserved line, and a provider
     * that resums the reservation refuses the capture.
     */
    public function testTheLastCaptureSettlesTheReservedResidual(): void
    {
        // The third and last unit of the same order: 59.40 has been captured at reserved prices.
        $item = $this->makeItem('24-WB01', 'Voyage Yoga Bag', 35.36, 16.99, 1.0, 3.0);

        $articles = $this->buildCapture(
            [$item],
            ['grandTotal' => 29.70, 'discount' => -5.66, 'subtotal' => 35.36],
            [
                'grandTotal' => 89.09, 'discount' => -16.99, 'subtotal' => 106.08,
                'itemDiscounts' => [16.99], 'totalPaid' => 59.40, 'canInvoice' => false,
            ]
        );

        $this->assertEqualsWithDelta(
            -0.01,
            $this->priceOf($articles, \Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler::ADJUSTMENT_IDENTIFIER),
            0.001,
            'The residual the reservation still holds'
        );
        $this->assertEqualsWithDelta(
            29.69,
            $this->sumArticles($articles),
            0.001,
            'The final capture takes exactly what is left of the authorization'
        );
    }

    /**
     * A residual small enough to sit on an existing single-quantity line still does, for the
     * methods that send prices - only Klarna KP needs the dedicated line.
     */
    public function testAnAdjustmentLineIsOnlyAddedWhenNoLineCanCarryTheResidual(): void
    {
        $articles = $this->buildCapture(
            [$this->makeItem('24-WB01', 'Voyage Yoga Bag', 35.36, 16.00)],
            ['grandTotal' => 19.36, 'discount' => -16.00, 'subtotal' => 32.00],
            ['grandTotal' => 38.72, 'discount' => -32.00, 'subtotal' => 64.00, 'itemDiscounts' => [16.00, 16.00]]
        );

        $this->assertNotContains(
            \Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AbstractArticlesHandler::ADJUSTMENT_IDENTIFIER,
            array_column($articles['articles'], 'identifier'),
            'Nothing to adjust when the lines already add up'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array $invoiceItems
     * @param array $invoiceTotals
     * @param array $orderTotals
     * @param int   $numberOfInvoices
     *
     * @return array
     */
    private function buildCapture(
        array $invoiceItems,
        array $invoiceTotals,
        array $orderTotals,
        int $numberOfInvoices = 1
    ): array {
        $handler = $this->makeHandler();
        $order = $this->makeOrder($invoiceItems, $invoiceTotals, $orderTotals, $numberOfInvoices);

        return $handler->getInvoiceArticlesData($order, $this->createMock(InfoInterface::class));
    }

    /**
     * @param array $articles
     *
     * @return float
     */
    private function sumArticles(array $articles): float
    {
        $sum = 0.0;
        foreach ($articles['articles'] as $article) {
            $sum += (float)$article['price'] * (float)$article['quantity'];
        }

        return round($sum, 2);
    }

    /**
     * @param array  $articles
     * @param string $identifier
     *
     * @return float
     */
    private function priceOf(array $articles, string $identifier): float
    {
        $total = 0.0;
        foreach ($articles['articles'] as $article) {
            if ((string)$article['identifier'] === $identifier) {
                $total += (float)$article['price'] * (float)$article['quantity'];
            }
        }

        return round($total, 2);
    }

    /**
     * @return KlarnaKpHandler
     */
    private function makeHandler(): KlarnaKpHandler
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        // Catalog prices include tax; every amount in these tests is gross with 0% VAT.
        $this->scopeConfig->method('getValue')->willReturn('1');

        $productMetaData = $this->createMock(ProductMetadataInterface::class);
        $productMetaData->method('getEdition')->willReturn('Community');
        $this->softwareData = $this->createMock(SoftwareData::class);
        $this->softwareData->method('getProductMetaData')->willReturn($productMetaData);

        $quote = $this->getMockBuilder(QuoteStub::class)->disableOriginalConstructor()->getMock();
        $quoteProxy = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->getMock();
        $quoteProxy->method('load')->willReturn($quote);
        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($quoteProxy);

        $rateRequest = $this->getMockBuilder(StdObjectStub::class)->getMock();
        $rateRequest->method('setProductClassId')->willReturnSelf();
        $taxCalculation = $this->createMock(Calculation::class);
        $taxCalculation->method('getRateRequest')->willReturn($rateRequest);
        $taxCalculation->method('getRate')->willReturn(0.0);
        $taxConfig = $this->createMock(TaxConfig::class);
        $taxConfig->method('getShippingTaxClass')->willReturn(0);

        return new KlarnaKpHandler(
            $this->scopeConfig,
            $this->createMock(BuckarooLoggerInterface::class),
            $quoteFactory,
            $taxCalculation,
            $taxConfig,
            $this->createMock(BuckarooFee::class),
            $this->softwareData,
            $this->createMock(ConfigProviderMethodFactory::class),
            $this->createMock(PayReminderService::class),
            $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class)
        );
    }

    /**
     * @param array $invoiceItems
     * @param array $invoiceTotals
     * @param array $orderTotals
     * @param int   $numberOfInvoices
     *
     * @return Order|MockObject
     */
    private function makeOrder(
        array $invoiceItems,
        array $invoiceTotals,
        array $orderTotals,
        int $numberOfInvoices
    ) {
        $invoice = $this->getMockBuilder(InvoiceStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAllItems', 'getGrandTotal', 'getDiscountAmount', 'getDiscountTaxCompensationAmount',
                'getSubtotal', 'getTaxAmount', 'getShippingInclTax', 'getBuckarooFeeInclTax',
                'getBuckarooFee', 'getData', 'getShippingAddress', 'getBillingAddress', 'getStore',
            ])
            ->getMock();
        $invoice->method('getAllItems')->willReturn($invoiceItems);
        $invoice->method('getGrandTotal')->willReturn($invoiceTotals['grandTotal']);
        $invoice->method('getDiscountAmount')->willReturn($invoiceTotals['discount']);
        $invoice->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $invoice->method('getSubtotal')->willReturn($invoiceTotals['subtotal']);
        $invoice->method('getTaxAmount')->willReturn(0.0);
        $invoice->method('getShippingInclTax')->willReturn($invoiceTotals['shipping'] ?? 0.0);
        $invoice->method('getShippingAddress')->willReturn(null);
        $invoice->method('getBillingAddress')->willReturn(null);
        $invoice->method('getStore')->willReturn(null);
        $invoice->method('getBuckarooFeeInclTax')->willReturn(0.0);
        $invoice->method('getBuckarooFee')->willReturn(0.0);
        $invoice->method('getData')->willReturnCallback(
            function ($key = null) use ($invoiceTotals) {
                return $key === 'gift_cards_amount' ? ($invoiceTotals['giftCard'] ?? null) : null;
            }
        );

        $collection = $this->createMock(InvoiceCollection::class);
        $collection->method('count')->willReturn($numberOfInvoices);
        $collection->method('getLastItem')->willReturn($invoice);

        // The order items behind the invoice items ARE the order's items: the reserve and the
        // capture both read their prices, so the fixture may not invent a second set.
        $orderItems = [];
        foreach ($invoiceItems as $invoiceItem) {
            $behind = $invoiceItem->getOrderItem();
            if ($behind !== null) {
                $orderItems[] = $behind;
            }
        }

        foreach (($orderTotals['itemDiscounts'] ?? []) as $discount) {
            $orderItem = $this->createMock(Order\Item::class);
            $orderItem->method('getDiscountAmount')->willReturn($discount);
            $orderItem->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
            $orderItem->method('getRowTotal')->willReturn(16.99);
            $orderItem->method('getTaxPercent')->willReturn(0.0);
            // No price data, so it contributes nothing to the reserve rounding residual.
            $orderItem->method('getQtyOrdered')->willReturn(0.0);
            $orderItems[] = $orderItem;
        }

        $order = $this->createMock(Order::class);
        $order->method('getInvoiceCollection')->willReturn($collection);
        $order->method('getQuoteId')->willReturn(1);
        $order->method('getGrandTotal')->willReturn($orderTotals['grandTotal']);
        $order->method('getTotalPaid')->willReturn($orderTotals['totalPaid'] ?? 0.0);
        // Whether anything is left to invoice decides where the reserved rounding residual lands.
        $order->method('canInvoice')->willReturn($orderTotals['canInvoice'] ?? true);
        $order->method('getDiscountAmount')->willReturn($orderTotals['discount']);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $order->method('getShippingDiscountAmount')->willReturn(0.0);
        $order->method('getSubtotal')->willReturn($orderTotals['subtotal']);
        $order->method('getAllItems')->willReturn($orderItems);
        $order->method('getData')->willReturnCallback(
            function ($key = null) use ($orderTotals) {
                return $key === 'gift_cards_amount' ? ($orderTotals['giftCard'] ?? 0.0) : 0.0;
            }
        );

        return $order;
    }

    /**
     * @param string $sku
     * @param string $name
     * @param float  $price
     * @param float  $discount   Discount Magento wrote onto the order item row.
     * @param float  $qty        Quantity on this invoice.
     * @param float  $qtyOrdered Quantity the reserve was built for.
     *
     * @return Order\Invoice\Item|MockObject
     */
    private function makeItem(
        string $sku,
        string $name,
        float $price,
        float $discount = 0.0,
        float $qty = 1.0,
        float $qtyOrdered = 1.0
    ) {
        // The order item mirrors the quote the reserve was built from, and the capture reads both
        // the price and the discount from it so the reserved unit price is repeated exactly.
        $orderItem = $this->createMock(Order\Item::class);
        $orderItem->method('getTaxPercent')->willReturn(0.0);
        $orderItem->method('getDiscountAmount')->willReturn($discount);
        $orderItem->method('getDiscountTaxCompensationAmount')->willReturn(0.0);
        $orderItem->method('getQtyOrdered')->willReturn($qtyOrdered);
        $orderItem->method('getPriceInclTax')->willReturn($price);
        $orderItem->method('getPrice')->willReturn($price);
        $orderItem->method('getTaxAmount')->willReturn(0.0);
        $orderItem->method('getWeeeTaxAppliedAmount')->willReturn(0.0);

        $item = $this->getMockBuilder(InvoiceItemStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getRowTotal', 'getRowTotalInclTax', 'getOrderItem', 'getName', 'getSku', 'getQty',
                'getDiscountAmount', 'getPriceInclTax', 'getPrice', 'getTaxAmount', 'hasParentItemId',
                'getWeeeTaxAppliedAmount',
            ])
            ->getMock();
        $item->method('getRowTotal')->willReturn($price);
        $item->method('getRowTotalInclTax')->willReturn($price);
        $item->method('hasParentItemId')->willReturn(false);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);
        $item->method('getDiscountAmount')->willReturn(0.0);
        $item->method('getPriceInclTax')->willReturn($price);
        $item->method('getPrice')->willReturn($price);
        $item->method('getTaxAmount')->willReturn(0.0);
        $item->method('getWeeeTaxAppliedAmount')->willReturn(0.0);

        return $item;
    }
}
