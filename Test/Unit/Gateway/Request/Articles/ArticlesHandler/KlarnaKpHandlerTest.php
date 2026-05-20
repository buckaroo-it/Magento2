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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the Klarna capture / gift-card bug.
 *
 * Root cause: getInvoiceArticlesData (capture path) did not call getAdditionalLines(),
 * so the gift card discount line (identifier = 6) sent during reserve was absent from
 * the capture request. reconcileArticlesWithGrandTotal then fired and inserted an
 * 'extra-fees' line that Klarna had never seen, causing a capture rejection:
 * "The following article numbers are unknown or not pending: extra-fees."
 *
 * Fix: getInvoiceArticlesData now calls getAdditionalLines() before reconciliation,
 * mirroring the reserve path in getOrderArticlesData.
 */
class KlarnaKpHandlerTest extends TestCase
{
    private ScopeConfigInterface|MockObject $scopeConfig;
    private BuckarooLoggerInterface|MockObject $logger;
    private QuoteFactory|MockObject $quoteFactory;
    private Calculation|MockObject $taxCalculation;
    private TaxConfig|MockObject $taxConfig;
    private BuckarooFee|MockObject $buckarooFee;
    private SoftwareData|MockObject $softwareData;
    private ConfigProviderMethodFactory|MockObject $configProviderFactory;
    private PayReminderService|MockObject $payReminderService;

    /** Staged invoice items set by buildHandler, consumed by makeOrder(). */
    private array $stagedItems = [];
    private float $stagedGrandTotal = 0.0;

    protected function setUp(): void
    {
        $this->scopeConfig       = $this->createMock(ScopeConfigInterface::class);
        $this->logger            = $this->createMock(BuckarooLoggerInterface::class);
        $this->quoteFactory      = $this->createMock(QuoteFactory::class);
        $this->taxCalculation    = $this->createMock(Calculation::class);
        $this->taxConfig         = $this->createMock(TaxConfig::class);
        $this->buckarooFee       = $this->createMock(BuckarooFee::class);
        $this->softwareData      = $this->createMock(SoftwareData::class);
        $this->configProviderFactory = $this->createMock(ConfigProviderMethodFactory::class);
        $this->payReminderService    = $this->createMock(PayReminderService::class);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * When a gift card partially pays the order the capture request must include
     * the gift card discount line (identifier = 6) and must NOT include 'extra-fees'.
     *
     * Before the fix 'extra-fees' appeared at the same position as the gift card
     * line during reserve, causing Klarna to reject the capture.
     */
    public function testCaptureWithGiftCardIncludesGiftCardLineAndNoExtraFees(): void
    {
        // Products €20 + €14 = €34, gift card -€6, invoice total €28.
        $handler = $this->buildHandler(
            items: [
                $this->makeItem('PROD1', 'Product 1', 20.00),
                $this->makeItem('PROD2', 'Product 2', 14.00),
            ],
            invoiceGrandTotal: 28.00,
            giftCardAmount: 6.00
        );

        $result = $handler->getInvoiceArticlesData(
            $this->makeOrder(),
            $this->createMock(InfoInterface::class)
        );

        $identifiers = array_column($result['articles'], 'identifier');

        $this->assertContains(
            6,
            $identifiers,
            'Gift card line (identifier=6) must be present in the capture request'
        );
        $this->assertNotContains(
            'extra-fees',
            $identifiers,
            '"extra-fees" must not appear — Klarna never saw it during the reserve'
        );
    }

    /**
     * Without a gift card the article sum equals the invoice grand total,
     * so reconciliation must not fire and 'extra-fees' must not appear.
     */
    public function testCaptureWithoutGiftCardHasNoExtraFeesAndNoGiftCardLine(): void
    {
        $handler = $this->buildHandler(
            items: [
                $this->makeItem('PROD1', 'Product 1', 20.00),
                $this->makeItem('PROD2', 'Product 2', 14.00),
            ],
            invoiceGrandTotal: 34.00,
            giftCardAmount: 0.0
        );

        $result = $handler->getInvoiceArticlesData(
            $this->makeOrder(),
            $this->createMock(InfoInterface::class)
        );

        $identifiers = array_column($result['articles'], 'identifier');

        $this->assertNotContains('extra-fees', $identifiers);
        $this->assertNotContains(6, $identifiers, 'No gift card line expected when no gift card was applied');
    }

    /**
     * The gift card article line must carry the correct negative price so that
     * the article sum precisely matches the invoice grand total.
     */
    public function testGiftCardLineHasCorrectNegativePrice(): void
    {
        $handler = $this->buildHandler(
            items: [$this->makeItem('PROD1', 'Product 1', 50.00)],
            invoiceGrandTotal: 33.99,
            giftCardAmount: 16.01
        );

        $result = $handler->getInvoiceArticlesData(
            $this->makeOrder(),
            $this->createMock(InfoInterface::class)
        );

        $giftCardLine = null;
        foreach ($result['articles'] as $article) {
            if (($article['identifier'] ?? null) === 6) {
                $giftCardLine = $article;
                break;
            }
        }

        $this->assertNotNull($giftCardLine, 'Gift card article line must be present');
        $this->assertEquals(-16.01, $giftCardLine['price'], 'Gift card price must be negative and match the gift card amount');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildHandler(array $items, float $invoiceGrandTotal, float $giftCardAmount): KlarnaKpHandler
    {
        // Stage invoice data so makeOrder() can use it.
        $this->stagedItems      = $items;
        $this->stagedGrandTotal = $invoiceGrandTotal;

        // Quote mock — getGiftCardsAmount / getRewardCurrencyAmount are Adobe Commerce methods
        // absent on CE, so they must be added via addMethods().
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getGiftCardsAmount', 'getRewardCurrencyAmount'])
            ->getMock();
        $quote->method('getGiftCardsAmount')->willReturn($giftCardAmount);
        $quote->method('getRewardCurrencyAmount')->willReturn(0.0);

        $quoteProxy = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->addMethods(['getGiftCardsAmount', 'getRewardCurrencyAmount'])
            ->getMock();
        $quoteProxy->method('load')->willReturn($quote);
        $quoteProxy->method('getGiftCardsAmount')->willReturn($giftCardAmount);
        $quoteProxy->method('getRewardCurrencyAmount')->willReturn(0.0);

        $this->quoteFactory->method('create')->willReturn($quoteProxy);

        // Price does not include tax → calculateProductPrice reads getPriceInclTax().
        $this->scopeConfig->method('getValue')->willReturn(false);

        // Tax / shipping (shipping = 0 in these tests so shipping line is skipped).
        $rateRequest = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['setProductClassId'])
            ->getMock();
        $rateRequest->method('setProductClassId')->willReturnSelf();
        $this->taxCalculation->method('getRateRequest')->willReturn($rateRequest);
        $this->taxCalculation->method('getRate')->willReturn(0.0);
        $this->taxConfig->method('getShippingTaxClass')->willReturn(0);

        // Edition = Community (no Enterprise store-credit path).
        $meta = $this->createMock(ProductMetadataInterface::class);
        $meta->method('getEdition')->willReturn('Community');
        $this->softwareData->method('getProductMetaData')->willReturn($meta);

        return new KlarnaKpHandler(
            $this->scopeConfig,
            $this->logger,
            $this->quoteFactory,
            $this->taxCalculation,
            $this->taxConfig,
            $this->buckarooFee,
            $this->softwareData,
            $this->configProviderFactory,
            $this->payReminderService
        );
    }

    private function makeOrder(): Order
    {
        // getBuckarooFeeInclTax / getBuckarooFee are Buckaroo extension attributes absent
        // from the base Invoice class, so they must be added via addMethods().
        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getGrandTotal', 'getTaxAmount', 'getShippingInclTax'])
            ->addMethods(['getBuckarooFeeInclTax', 'getBuckarooFee'])
            ->getMock();
        $invoice->method('getAllItems')->willReturn($this->stagedItems);
        $invoice->method('getGrandTotal')->willReturn($this->stagedGrandTotal);
        $invoice->method('getTaxAmount')->willReturn(0.0);
        $invoice->method('getShippingInclTax')->willReturn(0.0); // no shipping keeps mocking minimal
        $invoice->method('getBuckarooFeeInclTax')->willReturn(0.0);
        $invoice->method('getBuckarooFee')->willReturn(0.0);

        $collection = $this->createMock(InvoiceCollection::class);
        $collection->method('count')->willReturn(1);
        $collection->method('getLastItem')->willReturn($invoice);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInvoiceCollection', 'getQuoteId'])
            ->getMock();
        $order->method('getInvoiceCollection')->willReturn($collection);
        $order->method('getQuoteId')->willReturn(1);

        return $order;
    }

    private function makeItem(string $sku, string $name, float $price): Invoice\Item
    {
        $orderItem = $this->createMock(Order\Item::class);
        $orderItem->method('getTaxPercent')->willReturn(0.0);

        // Use createMock so PHPUnit handles real-vs-magic method split automatically.
        $item = $this->getMockBuilder(Invoice\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRowTotalInclTax', 'getOrderItem', 'getName', 'getSku', 'getQty', 'getDiscountAmount', 'getPriceInclTax'])
            ->addMethods(['hasParentItemId', 'getWeeeTaxAppliedAmount'])
            ->getMock();

        $item->method('getRowTotalInclTax')->willReturn($price);
        $item->method('hasParentItemId')->willReturn(false);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn(1.0);
        $item->method('getDiscountAmount')->willReturn(0.0);
        $item->method('getPriceInclTax')->willReturn($price);
        $item->method('getWeeeTaxAppliedAmount')->willReturn(0.0);

        return $item;
    }
}
