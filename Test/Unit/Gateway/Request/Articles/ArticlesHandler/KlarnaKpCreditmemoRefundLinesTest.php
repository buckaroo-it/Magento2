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
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

/**
 * The lines a credit memo refunds carry the article numbers and unit prices the reserve sent.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class KlarnaKpCreditmemoRefundLinesTest extends TestCase
{
    /**
     * Reserved shipping line, as getShippingCostsLine() builds it.
     */
    private const SHIPPING_LINE = ['articles' => [['identifier' => 2, 'quantity' => 1, 'price' => 4.95]]];

    /**
     * Reserved Buckaroo fee line, as getServiceCostLine() builds it.
     */
    private const FEE_LINE = ['articles' => [['identifier' => 1, 'quantity' => 1, 'price' => 2.5]]];

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
            $this->linesFor($orderItems, $creditmemoItems)
        );
    }

    /**
     * A line with no refunded quantity is passed over even when it has a row total, and does not
     * stop the lines after it.
     */
    public function testALineWithoutQuantityIsSkippedAndTheNextLineStillCounts(): void
    {
        $orderItems = [$this->orderItem(11, '24-MB01', 34.00), $this->orderItem(12, '24-WB01', 19.36)];
        $creditmemoItems = [
            $this->creditmemoItem($orderItems[0], 0, 34.00),
            $this->creditmemoItem($orderItems[1], 1),
        ];

        $this->assertSame(
            [['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36]],
            $this->linesFor($orderItems, $creditmemoItems)
        );
    }

    /**
     * A line the reserve did not send (no row total, such as a configurable's child) is passed
     * over, even with a quantity.
     */
    public function testALineWithoutRowTotalIsSkipped(): void
    {
        $orderItems = [$this->orderItem(11, 'MT07-S-Gray', 22.00), $this->orderItem(12, '24-WB01', 19.36)];
        $creditmemoItems = [
            $this->creditmemoItem($orderItems[0], 1, 0.0),
            $this->creditmemoItem($orderItems[1], 1),
        ];

        $this->assertSame(
            [['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36]],
            $this->linesFor($orderItems, $creditmemoItems)
        );
    }

    /**
     * Quantities come from the database as decimal strings.
     */
    public function testAQuantityStoredAsADecimalStringIsAWholeQuantity(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], '2.0000')];

        $this->assertSame(
            [['identifier' => '24-WB01', 'quantity' => 2, 'price' => 19.36]],
            $this->linesFor($orderItems, $creditmemoItems)
        );
    }

    public function testANumericSkuIsSentAsAString(): void
    {
        $orderItems = [$this->orderItem(11, 12345, 10.00)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1)];

        $this->assertSame(
            [['identifier' => '12345', 'quantity' => 1, 'price' => 10.00]],
            $this->linesFor($orderItems, $creditmemoItems)
        );
    }

    public function testLinesThatShareASkuUseTheUniqueReservedNumber(): void
    {
        $orderItems = [$this->orderItem(11, 'SH01', 10.00), $this->orderItem(12, 'SH01', 12.00)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[1], 1)];

        $this->assertSame(
            [['identifier' => 'SH01-12', 'quantity' => 1, 'price' => 12.00]],
            $this->linesFor($orderItems, $creditmemoItems, ['unique' => true])
        );
    }

    public function testAFractionalQuantityCannotBeNominated(): void
    {
        $orderItems = [$this->orderItem(11, 'CHEESE', 4.00), $this->orderItem(12, '24-WB01', 19.36)];
        $creditmemoItems = [
            $this->creditmemoItem($orderItems[0], 1.5),
            $this->creditmemoItem($orderItems[1], 1),
        ];

        $this->assertSame([], $this->linesFor($orderItems, $creditmemoItems, ['shipping' => 4.95]));
    }

    public function testAPartialPaymentIsNotNominated(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1)];

        $this->assertSame([], $this->linesFor($orderItems, $creditmemoItems, ['payRemainder' => true]));
    }

    public function testRefundedShippingNominatesTheReservedShippingLine(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1)];

        $this->assertSame(
            [
                ['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36],
                ['identifier' => '2', 'quantity' => 1, 'price' => 4.95],
            ],
            $this->linesFor($orderItems, $creditmemoItems, ['shipping' => 4.95])
        );
    }

    public function testAShippingOnlyRefundNominatesOnlyTheShippingLine(): void
    {
        $this->assertSame(
            [['identifier' => '2', 'quantity' => 1, 'price' => 4.95]],
            $this->linesFor([], [], ['shipping' => 4.95])
        );
    }

    public function testNoShippingOrFeeRefundedNominatesNeither(): void
    {
        $this->assertSame([], $this->linesFor([], [], ['shipping' => 0.0, 'fee' => 0.0, 'feeInclTax' => 0.0]));
    }

    public function testARefundedFeeNominatesTheReservedFeeLine(): void
    {
        $this->assertSame(
            [['identifier' => '1', 'quantity' => 1, 'price' => 2.5]],
            $this->linesFor([], [], ['fee' => 2.07, 'feeInclTax' => 0.0])
        );
    }

    public function testAFeeKnownOnlyInclTaxStillNominatesTheFeeLine(): void
    {
        $this->assertSame(
            [['identifier' => '1', 'quantity' => 1, 'price' => 2.5]],
            $this->linesFor([], [], ['fee' => 0.0, 'feeInclTax' => 2.5])
        );
    }

    public function testShippingAndFeeAreBothNominated(): void
    {
        $this->assertSame(
            [
                ['identifier' => '2', 'quantity' => 1, 'price' => 4.95],
                ['identifier' => '1', 'quantity' => 1, 'price' => 2.5],
            ],
            $this->linesFor([], [], ['shipping' => 4.95, 'fee' => 2.07, 'feeInclTax' => 2.5])
        );
    }

    /**
     * Reserved lines are reduced to one unit each, malformed entries are dropped, the identifier
     * becomes a string and the price is taken to the cent.
     */
    public function testReservedLinesAreReducedToOneUnitEach(): void
    {
        $shippingLine = ['articles' => [
            'not an array',
            ['description' => 'no identifier', 'price' => 9.99],
            ['identifier' => 2, 'quantity' => 3, 'price' => 4.954],
            ['identifier' => 'x', 'quantity' => 1],
        ]];

        $this->assertSame(
            [
                ['identifier' => '2', 'quantity' => 1, 'price' => 4.95],
                ['identifier' => 'x', 'quantity' => 1, 'price' => 0.0],
            ],
            $this->linesFor([], [], ['shipping' => 4.95, 'shippingLine' => $shippingLine])
        );
    }

    /**
     * The shipping (2) and fee (1) lines come from the same builders the reserve uses, so the
     * refund nominates the reserved lines at their reserved prices.
     */
    public function testShippingAndFeeAreNominatedAsTheReserveBuiltThem(): void
    {
        $orderItems = [$this->orderItem(11, '24-WB01', 19.36)];
        $creditmemoItems = [$this->creditmemoItem($orderItems[0], 1)];

        $this->assertSame(
            [
                ['identifier' => '24-WB01', 'quantity' => 1, 'price' => 19.36],
                ['identifier' => '2', 'quantity' => 1, 'price' => 4.95],
                ['identifier' => '1', 'quantity' => 1, 'price' => 2.5],
            ],
            $this->linesFor(
                $orderItems,
                $creditmemoItems,
                ['shipping' => 4.09, 'fee' => 2.07, 'feeInclTax' => 2.5, 'reserveBuilders' => true]
            )
        );
    }

    /**
     * A shipping discount is netted into the reserved shipping line, so the refund nominates it
     * at the discounted price the reserve sent.
     */
    public function testDiscountedShippingIsNominatedAtTheReservedDiscountedPrice(): void
    {
        $this->assertSame(
            [['identifier' => '2', 'quantity' => 1, 'price' => 4.0]],
            $this->linesFor(
                [],
                [],
                ['shipping' => 4.09, 'reserveBuilders' => true, 'orderData' => [
                    'shipping_amount' => 4.09,
                    'shipping_tax_amount' => 0.0,
                    'shipping_discount_amount' => 0.09,
                ]]
            )
        );
    }

    /**
     * @param array $orderItems
     * @param array $creditmemoItems
     * @param array $options unique, payRemainder, shipping, fee, feeInclTax, shippingLine, reserveBuilders, orderData
     *
     * @return array
     */
    private function linesFor(array $orderItems, array $creditmemoItems, array $options = []): array
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getStore', 'getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $order->method('getAllItems')->willReturn($orderItems);
        $order->setData(($options['orderData'] ?? []) + [
            'store_id' => 1,
            'shipping_incl_tax' => 4.95,
            'buckaroo_fee_incl_tax' => 2.5,
        ]);

        $creditmemo = $this->getMockBuilder(Order\Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getShippingAmount'])
            ->getMock();
        $creditmemo->method('getAllItems')->willReturn($creditmemoItems);
        $creditmemo->method('getShippingAmount')->willReturn($options['shipping'] ?? 0.0);
        $creditmemo->setData('buckaroo_fee', $options['fee'] ?? 0.0);
        $creditmemo->setData('buckaroo_fee_incl_tax', $options['feeInclTax'] ?? 0.0);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation', 'getCreditmemo'])
            ->getMock();
        $payment->method('getCreditmemo')->willReturn($creditmemo);
        $unique = $options['unique'] ?? false;
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn($key = null) => $key === KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS ? $unique : null
        );

        $payReminderService = $this->createMock(PayReminderService::class);
        $payReminderService->method('isPayRemainder')->willReturn($options['payRemainder'] ?? false);

        // 21% on shipping and on the fee; the rate request is a plain data object
        $taxCalculation = $this->createMock(Calculation::class);
        $taxCalculation->method('getRateRequest')->willReturn(new \Magento\Framework\DataObject());
        $taxCalculation->method('getRate')->willReturn(21.0);

        $handler = $this->getMockBuilder(KlarnaKpHandler::class)
            ->setConstructorArgs([
                $this->createMock(ScopeConfigInterface::class),
                $this->createMock(BuckarooLoggerInterface::class),
                $this->createMock(QuoteFactory::class),
                $taxCalculation,
                $this->createMock(TaxConfig::class),
                $this->createMock(BuckarooFee::class),
                $this->createMock(SoftwareData::class),
                $this->createMock(ConfigProviderMethodFactory::class),
                $payReminderService,
                $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class),
            ])
            // Stub the reserved shipping and fee lines, unless the test runs the real builders
            ->onlyMethods(empty($options['reserveBuilders']) ? ['getShippingCostsLine', 'getServiceCostLine'] : [])
            ->getMock();
        if (empty($options['reserveBuilders'])) {
            $handler->method('getShippingCostsLine')->willReturn($options['shippingLine'] ?? self::SHIPPING_LINE);
            $handler->method('getServiceCostLine')->willReturn(self::FEE_LINE);
        }

        return $handler->getCreditmemoRefundLines($order, $payment);
    }

    /**
     * @param int        $quoteItemId
     * @param string|int $sku
     * @param float      $priceInclTax
     *
     * @return Order\Item
     */
    private function orderItem(int $quoteItemId, $sku, float $priceInclTax)
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
     * @param Order\Item   $orderItem
     * @param float|string $qty
     * @param float|null   $rowTotalInclTax Defaults to the quantity times the unit price
     *
     * @return Order\Creditmemo\Item
     */
    private function creditmemoItem($orderItem, $qty, ?float $rowTotalInclTax = null)
    {
        $item = $this->getMockBuilder(Order\Creditmemo\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrderItem', 'getSku', 'getQty', 'getRowTotalInclTax'])
            ->getMock();
        $item->method('getOrderItem')->willReturn($orderItem);
        $item->method('getSku')->willReturn($orderItem->getSku());
        $item->method('getQty')->willReturn($qty);
        $item->method('getRowTotalInclTax')->willReturn(
            $rowTotalInclTax ?? (float)$qty * (float)$orderItem->getPriceInclTax()
        );

        return $item;
    }
}
