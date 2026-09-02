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

namespace Buckaroo\Magento2\Test\Unit\Plugin\Sales\Order;

use Buckaroo\Magento2\Plugin\Sales\Order\ClampCreditmemoShippingToInvoice;
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
            ->onlyMethods(['getInvoice', 'getDataUsingMethod', 'setDataUsingMethod'])
            ->getMock();
        $creditmemo->method('getInvoice')->willReturn($invoice);
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
}
