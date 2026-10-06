<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The item SKU of a product with custom options is the product SKU followed by every selected
 * option SKU, so it can be longer than a payment method accepts as an article identifier.
 *
 * Limits measured on the Buckaroo test gateway: In3 rejects more than 64 characters and Riverty
 * more than 100; Billink and Klarna KP accept 255, the length of the item SKU column. The gateway
 * counts UTF-16 code units, so an emoji counts as two. Zakelijk op Rekening is sent to the same In3
 * service and keeps its limit.
 */
class ArticleIdentifierLengthTest extends \Buckaroo\Magento2\Test\BaseTest
{
    private const HANDLER_NAMESPACE = 'Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\\';

    /**
     * Product SKU with four option SKUs, as reported in GitHub issue #1787 (69 characters).
     */
    private const OPTION_SKU = '8806097341987-2050000195006-2050000195457-8720015006669-2050000195266';

    /**
     * @param string $handler
     * @param int    $limit
     */
    #[DataProvider('limitedHandlerProvider')]
    public function testAnIdentifierWithinTheLimitIsSentUnchanged(string $handler, int $limit): void
    {
        $sku = str_repeat('A', $limit);

        $this->assertSame($sku, $this->identifierFor($handler, $sku));
    }

    /**
     * @param string $handler
     * @param int    $limit
     */
    #[DataProvider('limitedHandlerProvider')]
    public function testALongerIdentifierIsShortenedToTheLimit(string $handler, int $limit): void
    {
        foreach ([$limit + 1, 255] as $length) {
            $sku = substr(str_repeat(self::OPTION_SKU . '-', 4), 0, $length);

            $identifier = $this->identifierFor($handler, $sku);

            $this->assertSame($limit, mb_strlen($identifier), "A $length character SKU must fit in $limit");
            $this->assertStringStartsWith(mb_substr($sku, 0, $limit - 9), $identifier);
            $this->assertMatchesRegularExpression('/-[0-9a-f]{8}$/', $identifier);
        }
    }

    public static function limitedHandlerProvider(): array
    {
        return [
            'In3'                  => [self::HANDLER_NAMESPACE . 'CapayableIn3Handler', 64],
            'Zakelijk op Rekening' => [self::HANDLER_NAMESPACE . 'ZakelijkOpRekeningHandler', 64],
            'Riverty'              => [self::HANDLER_NAMESPACE . 'AfterpayHandler', 100],
        ];
    }

    /**
     * An order authorized by one release can be captured or refunded by the next, and Riverty and
     * Zakelijk op Rekening send the identifier again on those requests. The shortened value must
     * therefore never change between releases.
     */
    public function testTheReportedSkuAlwaysBecomesTheSameIn3Identifier(): void
    {
        $this->assertSame(
            '8806097341987-2050000195006-2050000195457-8720015006669-6caf0d31',
            $this->identifierFor(self::HANDLER_NAMESPACE . 'CapayableIn3Handler', self::OPTION_SKU)
        );
    }

    /**
     * Two option sets of the same product can share their first 64 characters; cutting alone
     * would give both lines the same identifier.
     */
    public function testSkusThatShareTheirStartGetDifferentIdentifiers(): void
    {
        $handler = self::HANDLER_NAMESPACE . 'CapayableIn3Handler';
        $otherSku = substr(self::OPTION_SKU, 0, -5) . '99999';

        $this->assertSame(substr(self::OPTION_SKU, 0, 64), substr($otherSku, 0, 64));
        $this->assertNotSame(
            $this->identifierFor($handler, self::OPTION_SKU),
            $this->identifierFor($handler, $otherSku)
        );
    }

    public function testAnAccentedSkuCountsOneUnitPerCharacter(): void
    {
        $handler = self::HANDLER_NAMESPACE . 'CapayableIn3Handler';

        $this->assertSame(str_repeat('é', 64), $this->identifierFor($handler, str_repeat('é', 64)));

        $identifier = $this->identifierFor($handler, str_repeat('é', 70));

        $this->assertTrue(mb_check_encoding($identifier, 'UTF-8'));
        $this->assertSame(64, mb_strlen($identifier));
        $this->assertStringStartsWith(str_repeat('é', 55) . '-', $identifier);
    }

    /**
     * 63 letters and an emoji are 64 characters but 65 UTF-16 units, which the gateway rejects.
     */
    public function testAnEmojiCountsTwoUnits(): void
    {
        $identifier = $this->identifierFor(
            self::HANDLER_NAMESPACE . 'CapayableIn3Handler',
            str_repeat('A', 63) . "\u{1F600}"
        );

        $this->assertSame(64, $this->utf16Length($identifier));
        $this->assertMatchesRegularExpression('/^A{55}-[0-9a-f]{8}$/', $identifier);
    }

    /**
     * An emoji that straddles the cut is dropped whole, never split into half a character.
     */
    public function testAnEmojiAtTheCutIsDroppedWhole(): void
    {
        $identifier = $this->identifierFor(
            self::HANDLER_NAMESPACE . 'CapayableIn3Handler',
            str_repeat('A', 54) . "\u{1F600}" . str_repeat('B', 20)
        );

        $this->assertTrue(mb_check_encoding($identifier, 'UTF-8'));
        $this->assertLessThanOrEqual(64, $this->utf16Length($identifier));
        $this->assertMatchesRegularExpression('/^A{54}-[0-9a-f]{8}$/', $identifier);
    }

    /**
     * @param string $handler
     */
    #[DataProvider('unlimitedHandlerProvider')]
    public function testMethodsThatAcceptTheWholeSkuColumnSendItUnchanged(string $handler): void
    {
        $sku = substr(str_repeat(self::OPTION_SKU . '-', 4), 0, 255);

        $this->assertSame($sku, $this->identifierFor($handler, $sku));
    }

    public static function unlimitedHandlerProvider(): array
    {
        return [
            'Billink'   => [self::HANDLER_NAMESPACE . 'BillinkHandler'],
            'Klarna KP' => [self::HANDLER_NAMESPACE . 'KlarnaKpHandler'],
            'default'   => [self::HANDLER_NAMESPACE . 'DefaultHandler'],
        ];
    }

    public function testAnItemWithoutASkuKeepsNoIdentifier(): void
    {
        $this->assertNull($this->identifierFor(self::HANDLER_NAMESPACE . 'CapayableIn3Handler', null));
    }

    public function testTheIn3PaymentLinesUseTheShortenedIdentifier(): void
    {
        $instance = $this->getFakeMock(self::HANDLER_NAMESPACE . 'CapayableIn3Handler')
            ->onlyMethods(['calculateProductPrice', 'getItemTax'])
            ->getMock();
        $instance->method('calculateProductPrice')->willReturn(20.10);
        $instance->method('getItemTax')->willReturn(21.0);
        $this->setProperty('quote', $this->quoteWith(self::OPTION_SKU), $instance);

        $lines = $this->invoke('getItemsLinesWithDiscount', $instance);

        $this->assertSame(
            '8806097341987-2050000195006-2050000195457-8720015006669-6caf0d31',
            $lines[0]['identifier']
        );
    }

    /**
     * Riverty receives articles on the authorize, the capture and the refund. All three must
     * carry the same identifier for the same item.
     */
    public function testRivertySendsTheSameIdentifierOnAuthorizeCaptureAndRefund(): void
    {
        $sku = substr(str_repeat(self::OPTION_SKU . '-', 3), 0, 150);
        $handler = self::HANDLER_NAMESPACE . 'AfterpayHandler';

        $authorize = $this->authorizeLines($handler, $sku);
        $capture = $this->captureLines($handler, $sku);
        $refund = $this->refundLines($handler, $sku);

        $this->assertSame(100, mb_strlen($authorize[0]['identifier']));
        $this->assertSame($authorize[0]['identifier'], $capture[0]['identifier']);
        $this->assertSame($authorize[0]['identifier'], $refund['articles'][0]['identifier']);
    }

    /**
     * Zakelijk op Rekening captures through the shared invoice lines and keeps the In3 limit.
     */
    public function testZakelijkOpRekeningCapturesWithTheShortenedIdentifier(): void
    {
        $capture = $this->captureLines(self::HANDLER_NAMESPACE . 'ZakelijkOpRekeningHandler', self::OPTION_SKU);

        $this->assertSame(
            '8806097341987-2050000195006-2050000195457-8720015006669-6caf0d31',
            $capture[0]['identifier']
        );
    }

    /**
     * @param string $value
     *
     * @return int
     */
    private function utf16Length(string $value): int
    {
        return intdiv(strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * @param string      $handler
     * @param string|null $sku
     *
     * @return string|null
     */
    private function identifierFor(string $handler, ?string $sku): ?string
    {
        $item = $this->getFakeMock('Magento\Quote\Model\Quote\Item')->onlyMethods(['getSku'])->getMock();
        $item->method('getSku')->willReturn($sku);

        $instance = $this->getFakeMock($handler)->onlyMethods([])->getMock();

        return $this->invokeArgs('getIdentifier', [$item], $instance);
    }

    /**
     * @param string $handler
     * @param string $sku
     *
     * @return array
     */
    private function authorizeLines(string $handler, string $sku): array
    {
        $instance = $this->getFakeMock($handler)
            ->onlyMethods(['getDiscountedProductPrice', 'getUnitDiscount', 'getItemTax', 'getProductImageUrl'])
            ->getMock();
        $instance->method('getDiscountedProductPrice')->willReturn(20.10);
        $instance->method('getUnitDiscount')->willReturn(0.0);
        $instance->method('getItemTax')->willReturn(21.0);
        $instance->method('getProductImageUrl')->willReturn('');
        $this->setProperty('quote', $this->quoteWith($sku), $instance);

        return $this->invoke('getItemsLines', $instance);
    }

    /**
     * @param string $handler
     * @param string $sku
     *
     * @return array
     */
    private function captureLines(string $handler, string $sku): array
    {
        $instance = $this->getFakeMock($handler)
            ->onlyMethods(['getDiscountedProductPrice', 'getReservedUnitDiscount', 'getItemTax'])
            ->getMock();
        $instance->method('getDiscountedProductPrice')->willReturn(20.10);
        $instance->method('getReservedUnitDiscount')->willReturn(0.0);
        $instance->method('getItemTax')->willReturn(21.0);

        $invoiceItem = $this->getFakeMock('Buckaroo\Magento2\Test\Unit\Stubs\InvoiceItemStub')
            ->onlyMethods(['getRowTotalInclTax', 'hasParentItemId', 'getName', 'getSku', 'getQty', 'getOrderItem'])
            ->getMock();
        $invoiceItem->method('getRowTotalInclTax')->willReturn(20.10);
        $invoiceItem->method('hasParentItemId')->willReturn(false);
        $invoiceItem->method('getName')->willReturn('Product with options');
        $invoiceItem->method('getSku')->willReturn($sku);
        $invoiceItem->method('getQty')->willReturn(1.0);
        $invoiceItem->method('getOrderItem')->willReturn($this->getFakeMock('Magento\Sales\Model\Order\Item', true));

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->onlyMethods(['getAllItems'])->getMock();
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);

        return $this->invokeArgs('getInvoiceItemsLines', [$invoice], $instance);
    }

    /**
     * @param string $handler
     * @param string $sku
     *
     * @return array
     */
    private function refundLines(string $handler, string $sku): array
    {
        $instance = $this->getFakeMock($handler)
            ->onlyMethods(['calculateProductPrice', 'getItemTax', 'getShippingCostsLine'])
            ->getMock();
        $instance->method('calculateProductPrice')->willReturn(20.10);
        $instance->method('getItemTax')->willReturn(21.0);
        $instance->method('getShippingCostsLine')->willReturn([]);

        $payReminder = $this->getFakeMock('Buckaroo\Magento2\Service\PayReminderService', true);
        $payReminder->method('isPayRemainder')->willReturn(false);
        $this->setProperty('payReminderService', $payReminder, $instance);

        $creditmemoItem = $this->getFakeMock('Buckaroo\Magento2\Test\Unit\Stubs\CreditmemoItemStub')
            ->onlyMethods([
                'getRowTotalInclTax', 'hasParentItemId', 'getName', 'getSku', 'getQty', 'getDiscountAmount', 'getOrderItem',
            ])
            ->getMock();
        $creditmemoItem->method('getRowTotalInclTax')->willReturn(20.10);
        $creditmemoItem->method('hasParentItemId')->willReturn(false);
        $creditmemoItem->method('getName')->willReturn('Product with options');
        $creditmemoItem->method('getSku')->willReturn($sku);
        $creditmemoItem->method('getQty')->willReturn(1.0);
        $creditmemoItem->method('getDiscountAmount')->willReturn(0.0);
        $creditmemoItem->method('getOrderItem')->willReturn($this->getFakeMock('Magento\Sales\Model\Order\Item', true));

        $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')
            ->onlyMethods(['getAllItems', 'getGrandTotal'])
            ->getMock();
        $creditmemo->method('getAllItems')->willReturn([$creditmemoItem]);
        $creditmemo->method('getGrandTotal')->willReturn(20.10);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->onlyMethods(['hasCreditmemos'])->getMock();
        $order->method('hasCreditmemos')->willReturn(true);

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')
            ->onlyMethods(['getCreditmemo', 'getOrder'])
            ->getMock();
        $payment->method('getCreditmemo')->willReturn($creditmemo);
        $payment->method('getOrder')->willReturn($order);

        return $instance->getCreditMemoArticlesData($order, $payment);
    }

    /**
     * @param string $sku
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function quoteWith(string $sku)
    {
        $item = $this->getFakeMock('Buckaroo\Magento2\Test\Unit\Stubs\QuoteItemStub')->getMock();
        $item->method('getRowTotalInclTax')->willReturn(20.10);
        $item->method('hasParentItemId')->willReturn(false);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getProductType')->willReturn('simple');
        $item->method('getName')->willReturn('Product with options');
        $item->method('getSku')->willReturn($sku);
        $item->method('getTotalQty')->willReturn(1.0);
        $item->method('getDiscountAmount')->willReturn(0.0);

        $quote = $this->getFakeMock('Magento\Quote\Model\Quote')->onlyMethods(['getAllItems'])->getMock();
        $quote->method('getAllItems')->willReturn([$item]);

        return $quote;
    }
}
