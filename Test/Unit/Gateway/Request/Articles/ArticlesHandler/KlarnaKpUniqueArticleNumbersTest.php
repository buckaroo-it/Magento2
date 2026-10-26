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
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

/**
 * Lines that share a SKU get unique article numbers, the same on the reserve and the capture.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class KlarnaKpUniqueArticleNumbersTest extends TestCase
{
    public function testTheReserveGivesLinesWithTheSameSkuUniqueArticleNumbers(): void
    {
        $quote = $this->quoteWith($items);
        $first = $this->quoteItem($quote, 11, 'SH00088558');
        $second = $this->quoteItem($quote, 12, 'SH00088558');
        $other = $this->quoteItem($quote, 13, '24-WB01');
        $items = [$first, $second, $other];
        $handler = $this->makeHandler();
        $handler->setPayment($this->createMock(InfoInterface::class));

        $this->assertSame('SH00088558-11', $this->identifierOf($handler, $first));
        $this->assertSame('SH00088558-12', $this->identifierOf($handler, $second));
        $this->assertSame('24-WB01', $this->identifierOf($handler, $other));
    }

    public function testAConfigurableChildDoesNotCountAsADuplicateSku(): void
    {
        $quote = $this->quoteWith($items);
        $parent = $this->quoteItem($quote, 21, 'MH07-M-Black');
        $child = $this->quoteItem($quote, 22, 'MH07-M-Black', 21);
        $items = [$parent, $child];
        $handler = $this->makeHandler();
        $handler->setPayment($this->createMock(InfoInterface::class));

        $this->assertSame('MH07-M-Black', $this->identifierOf($handler, $parent));
    }

    public function testACaptureUsesTheArticleNumberOfTheReservedLine(): void
    {
        $orderItems = [$this->orderItem(11, 'SH00088558'), $this->orderItem(12, 'SH00088558')];
        $handler = $this->handlerForOrder($orderItems, true);

        $this->assertSame('SH00088558-12', $this->identifierOf($handler, $this->invoiceItem($orderItems[1])));
    }

    public function testACaptureOnAReservationWithoutUniqueNumbersKeepsTheSku(): void
    {
        $orderItems = [$this->orderItem(11, 'SH00088558'), $this->orderItem(12, 'SH00088558')];
        $handler = $this->handlerForOrder($orderItems, false);

        $this->assertSame('SH00088558', $this->identifierOf($handler, $this->invoiceItem($orderItems[1])));
    }

    public function testDuplicateSkusAreDetectedOnTheOrder(): void
    {
        $duplicate = $this->handlerForOrder([$this->orderItem(1, 'A'), $this->orderItem(2, 'A')], false);
        $distinct = $this->handlerForOrder([$this->orderItem(1, 'A'), $this->orderItem(2, 'B')], false);
        $withChild = $this->handlerForOrder([$this->orderItem(1, 'A'), $this->orderItem(2, 'A', 1)], false);

        $this->assertTrue($duplicate->hasDuplicateSku());
        $this->assertFalse($distinct->hasDuplicateSku());
        $this->assertFalse($withChild->hasDuplicateSku());
    }

    public function testUpdateLinesThatCarryEveryUniqueNumberMissNothing(): void
    {
        $handler = $this->handlerForOrder(
            [$this->orderItem(11, 'A'), $this->orderItem(12, 'A'), $this->orderItem(13, 'B')],
            false
        );

        $this->assertSame([], $handler->getMissingUniqueArticleNumbers([
            ['identifier' => 'A-11'], ['identifier' => 'A-12'], ['identifier' => 'B'],
        ]));
    }

    public function testUpdateLinesWithoutTheProductLinesMissEveryUniqueNumber(): void
    {
        $handler = $this->handlerForOrder([$this->orderItem(11, 'A'), $this->orderItem(12, 'A')], false);

        $this->assertSame(
            ['A-11', 'A-12'],
            $handler->getMissingUniqueArticleNumbers([['identifier' => 'extra-fees']])
        );
    }

    public function testUpdateLinesThatStillShareTheSkuMissTheUniqueNumbers(): void
    {
        $handler = $this->handlerForOrder([$this->orderItem(11, 'A'), $this->orderItem(12, 'A')], false);

        $this->assertSame(
            ['A-11', 'A-12'],
            $handler->getMissingUniqueArticleNumbers([['identifier' => 'A'], ['identifier' => 'A']])
        );
    }

    public function testTheCartIsLoadedWhateverStoreTheRequestRunsIn(): void
    {
        $quote = $this->createMock(Quote::class);
        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($quote);
        $quoteResource = $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class);
        $quoteResource->expects($this->once())->method('loadByIdWithoutStore')->with($quote, 371);
        $quoteResource->expects($this->never())->method('load');

        $order = $this->createMock(Order::class);
        $order->method('getQuoteId')->willReturn(371);
        $handler = $this->makeHandler($quoteFactory, $quoteResource);
        $handler->setOrder($order);

        $this->assertSame($quote, $handler->getQuote());
    }

    /**
     * @param KlarnaKpHandler $handler
     * @param object          $item
     *
     * @return string
     */
    private function identifierOf(KlarnaKpHandler $handler, $item): string
    {
        $method = new \ReflectionMethod($handler, 'getIdentifier');
        $method->setAccessible(true);

        return (string)$method->invoke($handler, $item);
    }

    /**
     * A quote whose items are filled in once they exist; each item refers back to it.
     *
     * @param array|null $items
     *
     * @return Quote
     */
    private function quoteWith(&$items): Quote
    {
        $items = [];
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllItems')->willReturnCallback(function () use (&$items) {
            return $items;
        });

        return $quote;
    }

    /**
     * @param array $orderItems
     * @param bool  $uniqueArticleNumbers
     *
     * @return KlarnaKpHandler
     */
    private function handlerForOrder(array $orderItems, bool $uniqueArticleNumbers): KlarnaKpHandler
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn($orderItems);

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn($key = null) => $key === KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS ? $uniqueArticleNumbers : null
        );

        $handler = $this->makeHandler();
        $handler->setOrder($order);
        $handler->setPayment($payment);

        return $handler;
    }

    /**
     * @param Quote    $quote
     * @param int      $id
     * @param string   $sku
     * @param int|null $parentId
     *
     * @return Quote\Item
     */
    private function quoteItem(Quote $quote, int $id, string $sku, ?int $parentId = null)
    {
        $item = $this->getMockBuilder(Quote\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getSku', 'getParentItem', 'getQuote'])
            ->getMock();
        $item->method('getQuote')->willReturn($quote);
        $item->method('getId')->willReturn($id);
        $item->method('getSku')->willReturn($sku);
        $item->method('getParentItem')->willReturn(null);
        // parent_item_id is plain item data, read through the magic getter.
        $item->setData('parent_item_id', $parentId);

        return $item;
    }

    /**
     * @param int      $quoteItemId
     * @param string   $sku
     * @param int|null $parentId
     *
     * @return Order\Item
     */
    private function orderItem(int $quoteItemId, string $sku, ?int $parentId = null)
    {
        $item = $this->createMock(Order\Item::class);
        $item->method('getQuoteItemId')->willReturn($quoteItemId);
        $item->method('getSku')->willReturn($sku);
        $item->method('getParentItemId')->willReturn($parentId);
        $item->method('getParentItem')->willReturn(null);

        return $item;
    }

    /**
     * @param Order\Item $orderItem
     *
     * @return Order\Invoice\Item
     */
    private function invoiceItem($orderItem)
    {
        $item = $this->createMock(Order\Invoice\Item::class);
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getSku')->willReturn($orderItem->getSku());

        return $item;
    }

    /**
     * @param QuoteFactory|null                              $quoteFactory
     * @param \Magento\Quote\Model\ResourceModel\Quote|null $quoteResource
     *
     * @return KlarnaKpHandler
     */
    private function makeHandler($quoteFactory = null, $quoteResource = null): KlarnaKpHandler
    {
        return new KlarnaKpHandler(
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $quoteFactory ?? $this->createMock(QuoteFactory::class),
            $this->createMock(Calculation::class),
            $this->createMock(TaxConfig::class),
            $this->createMock(BuckarooFee::class),
            $this->createMock(SoftwareData::class),
            $this->createMock(ConfigProviderMethodFactory::class),
            $this->createMock(PayReminderService::class),
            $quoteResource ?? $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class)
        );
    }
}
