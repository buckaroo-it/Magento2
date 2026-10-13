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

namespace Buckaroo\Magento2\Test\Unit\Plugin\Sales\Order;

use Buckaroo\Magento2\Plugin\Sales\Order\ClampCreditmemoShippingToInvoice;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\Shipping as ShippingTotal;
use Magento\Sales\Model\Order\Invoice;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * `CreditmemoFactory::getShippingAmount()` clamps to the invoice only in its
 * excluding-tax branch. With shipping displayed INCLUDING tax it works off the order, so a credit
 * memo for a later invoice of a per-shipment order is seeded with shipping that invoice never
 * charged - and goes negative once shipping has been refunded. `Total\Discount` runs before
 * `Total\Shipping` and feeds that straight into its shipping-discount branch.
 */
class ClampCreditmemoShippingToInvoiceTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = ClampCreditmemoShippingToInvoice::class;

    /**
     * @param float $memoShipping
     * @param float $invoiceShipping
     * @param float $expected
     */
    #[DataProvider('shippingProvider')]
    public function testTheMemoNeverRefundsShippingItsInvoiceDidNotCharge(
        float $memoShipping,
        float $invoiceShipping,
        float $expected
    ): void {
        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getId')->willReturn(181);
        $invoice->method('getBaseShippingAmount')->willReturn($invoiceShipping);
        $invoice->method('getShippingAmount')->willReturn($invoiceShipping);
        $invoice->method('getBaseShippingInclTax')->willReturn($invoiceShipping);
        $invoice->method('getShippingInclTax')->willReturn($invoiceShipping);
        $invoice->method('getBaseShippingTaxAmount')->willReturn(0.0);
        $invoice->method('getShippingTaxAmount')->willReturn(0.0);

        $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')
            ->onlyMethods(['getInvoice', 'getOrder', 'getDataUsingMethod', 'setDataUsingMethod'])
            ->getMock();
        $creditmemo->method('getInvoice')->willReturn($invoice);
        $creditmemo->method('getOrder')->willReturn($this->getOrder());
        $creditmemo->method('getDataUsingMethod')->willReturn($memoShipping);

        $written = [];
        $creditmemo->method('setDataUsingMethod')->willReturnCallback(
            function ($key, $value) use (&$written, $creditmemo) {
                $written[$key] = $value;
                return $creditmemo;
            }
        );

        $this->getInstance()->beforeCollectTotals($creditmemo);

        // Zeroing the amount alone is not enough: Total\Discount reads base_shipping_incl_tax as
        // soon as base_shipping_amount is falsy, so every field has to be clamped.
        foreach ([
            'base_shipping_amount',
            'shipping_amount',
            'base_shipping_incl_tax',
            'shipping_incl_tax',
        ] as $key) {
            $this->assertArrayHasKey($key, $written, $key . ' must be clamped too');
            $this->assertEqualsWithDelta($expected, $written[$key], 0.001, $key);
        }
    }

    public static function shippingProvider(): array
    {
        return [
            'an invoice without shipping refunds none' => [12.10, 0.0, 0.0],
            'a negative seed is floored at zero' => [-1.68, 0.0, 0.0],
            'the first invoice keeps its shipping' => [12.10, 12.10, 12.10],
            'a partial shipping refund is left alone' => [5.00, 12.10, 5.00],
        ];
    }

    /**
     * A credit memo raised against the whole order has no invoice to measure against.
     */
    public function testAMemoWithoutAnInvoiceIsLeftAlone(): void
    {
        $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')
            ->onlyMethods(['getInvoice', 'setDataUsingMethod'])
            ->getMock();
        $creditmemo->method('getInvoice')->willReturn(null);
        $creditmemo->expects($this->never())->method('setDataUsingMethod');

        $this->getInstance()->beforeCollectTotals($creditmemo);
    }

    /**
     * Until collectTotals() runs, base_shipping_amount is the requested refund. With
     * shipping displayed including tax that request is gross, so it is bounded by the gross invoice
     * shipping - bounding it by the net amount turned a full EUR 2.99 refund into EUR 2.47.
     *
     * @param bool  $displayInclTax
     * @param float $requested
     * @param float $invoiceNet
     * @param float $invoiceGross
     * @param float $expected
     */
    #[DataProvider('requestedShippingProvider')]
    public function testTheRequestedShippingIsBoundedInTheUnitCoreReadsItIn(
        bool $displayInclTax,
        float $requested,
        float $invoiceNet,
        float $invoiceGross,
        float $expected
    ): void {
        $creditmemo = $this->getCreditmemo($this->getInvoice($invoiceNet, $invoiceGross));
        $creditmemo->setBaseShippingAmount($requested);

        $this->getPlugin($this->getTaxConfig($displayInclTax))->beforeCollectTotals($creditmemo);

        $this->assertEqualsWithDelta($expected, (float)$creditmemo->getBaseShippingAmount(), 0.0001);
    }

    public static function requestedShippingProvider(): array
    {
        return [
            'incl. tax: a full refund keeps the gross amount'      => [true, 2.99, 2.47, 2.99, 2.99],
            'incl. tax: a partial refund is left alone'            => [true, 1.50, 2.47, 2.99, 1.50],
            'incl. tax: more than the invoice charged is capped'   => [true, 5.00, 2.47, 2.99, 2.99],
            'incl. tax: an invoice without shipping refunds none'  => [true, 2.99, 0.0, 0.0, 0.0],
            'excl. tax: a full refund keeps the net amount'        => [false, 2.47, 2.47, 2.99, 2.47],
            'excl. tax: the gross amount is capped at the net one' => [false, 2.99, 2.47, 2.99, 2.47],
            'excl. tax: an invoice without shipping refunds none'  => [false, 2.47, 0.0, 0.0, 0.0],
        ];
    }

    /**
     * The plugin together with core Total\Shipping, the collector that turns the request into the
     * refunded amounts. EUR 2.99 shipping incl. 21% VAT (2.47 + 0.52), as in the BTI-1606 report.
     *
     * @param bool $displayInclTax
     * @param float $requested
     * @param float $expectedNet
     * @param float $expectedGross
     * @throws LocalizedException
     */
    #[DataProvider('refundedShippingProvider')]
    public function testCoreRefundsTheShippingTheInvoiceCharged(
        bool $displayInclTax,
        float $requested,
        float $expectedNet,
        float $expectedGross
    ): void {
        $taxConfig = $this->getTaxConfig($displayInclTax);
        $creditmemo = $this->getCreditmemo($this->getInvoice(2.47, 2.99));
        $creditmemo->setBaseShippingAmount($requested);

        $this->getPlugin($taxConfig)->beforeCollectTotals($creditmemo);
        $this->getShippingTotal($taxConfig)->collect($creditmemo);

        $this->assertEqualsWithDelta($expectedNet, (float)$creditmemo->getBaseShippingAmount(), 0.0001, 'net');
        $this->assertEqualsWithDelta($expectedGross, (float)$creditmemo->getBaseShippingInclTax(), 0.0001, 'gross');
    }

    public static function refundedShippingProvider(): array
    {
        return [
            'incl. tax: full refund'    => [true, 2.99, 2.47, 2.99],
            'incl. tax: partial refund' => [true, 1.50, 1.24, 1.50],
            'excl. tax: full refund'    => [false, 2.47, 2.47, 2.99],
            'excl. tax: partial refund' => [false, 1.00, 1.00, 1.21],
        ];
    }

    /**
     * A later invoice of a per-shipment order charged no shipping; core must refund none of it in
     * either display mode, although CreditmemoFactory seeds the including-tax request from the order.
     */
    #[DataProvider('displayModeProvider')]
    public function testCoreRefundsNoShippingOnAnInvoiceThatChargedNone(bool $displayInclTax): void
    {
        $taxConfig = $this->getTaxConfig($displayInclTax);
        $creditmemo = $this->getCreditmemo($this->getInvoice(0.0, 0.0));
        $creditmemo->setBaseShippingAmount($displayInclTax ? 2.99 : 2.47);

        $this->getPlugin($taxConfig)->beforeCollectTotals($creditmemo);
        $this->getShippingTotal($taxConfig)->collect($creditmemo);

        $this->assertEqualsWithDelta(0.0, (float)$creditmemo->getBaseShippingAmount(), 0.0001);
        $this->assertEqualsWithDelta(0.0, (float)$creditmemo->getBaseShippingInclTax(), 0.0001);
    }

    public static function displayModeProvider(): array
    {
        return ['shipping displayed incl. tax' => [true], 'shipping displayed excl. tax' => [false]];
    }

    /**
     * @param TaxConfig $taxConfig
     * @return ClampCreditmemoShippingToInvoice
     */
    private function getPlugin($taxConfig): ClampCreditmemoShippingToInvoice
    {
        return new ClampCreditmemoShippingToInvoice($taxConfig);
    }

    /**
     * @param bool $displayInclTax
     * @return TaxConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getTaxConfig(bool $displayInclTax)
    {
        $taxConfig = $this->createMock(TaxConfig::class);
        $taxConfig->method('displaySalesShippingInclTax')->willReturn($displayInclTax);
        $taxConfig->method('getCalculationSequence')->willReturn(TaxCalculation::CALC_TAX_AFTER_DISCOUNT_ON_EXCL);

        return $taxConfig;
    }

    /**
     * @param TaxConfig $taxConfig
     * @return ShippingTotal
     */
    private function getShippingTotal($taxConfig): ShippingTotal
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnCallback(fn ($amount) => round((float)$amount, 2));

        $shippingTotal = new ShippingTotal($priceCurrency);
        // Total\Shipping fetches its tax config from the object manager; hand it the same one.
        $property = new \ReflectionProperty(ShippingTotal::class, 'taxConfig');
        $property->setValue($shippingTotal, $taxConfig);

        return $shippingTotal;
    }

    /**
     * @param float $net
     * @param float $gross
     * @return Invoice|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getInvoice(float $net, float $gross)
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn(181);
        $invoice->method('getBaseShippingAmount')->willReturn($net);
        $invoice->method('getShippingAmount')->willReturn($net);
        $invoice->method('getBaseShippingInclTax')->willReturn($gross);
        $invoice->method('getShippingInclTax')->willReturn($gross);
        $invoice->method('getBaseShippingTaxAmount')->willReturn(round($gross - $net, 2));
        $invoice->method('getShippingTaxAmount')->willReturn(round($gross - $net, 2));

        return $invoice;
    }

    /**
     * A credit memo that keeps its data like the real one; only the order and invoice are doubled.
     *
     * @param Invoice $invoice
     * @return Creditmemo|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getCreditmemo($invoice)
    {
        $creditmemo = $this->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInvoice', 'getOrder'])
            ->getMock();
        $creditmemo->method('getInvoice')->willReturn($invoice);
        $creditmemo->method('getOrder')->willReturn($this->getOrder());

        return $creditmemo;
    }

    /**
     * An order that charged EUR 2.99 shipping incl. 21% VAT and refunded none of it yet.
     *
     * @return Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getOrder()
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingAmount')->willReturn(2.47);
        $order->method('getBaseShippingAmount')->willReturn(2.47);
        $order->method('getShippingInclTax')->willReturn(2.99);
        $order->method('getBaseShippingInclTax')->willReturn(2.99);
        $order->method('getShippingTaxAmount')->willReturn(0.52);
        $order->method('getBaseShippingTaxAmount')->willReturn(0.52);
        $order->method('getShippingRefunded')->willReturn(0.0);
        $order->method('getBaseShippingRefunded')->willReturn(0.0);
        $order->method('getShippingTaxRefunded')->willReturn(0.0);
        $order->method('getBaseShippingTaxRefunded')->willReturn(0.0);
        $order->method('getCreditmemosCollection')->willReturn(new \ArrayIterator([]));

        return $order;
    }
}
