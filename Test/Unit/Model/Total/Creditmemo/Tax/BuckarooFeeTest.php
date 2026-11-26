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

namespace Buckaroo\Magento2\Test\Unit\Model\Total\Creditmemo\Tax;

use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Buckaroo\Magento2\Model\Total\Creditmemo\Tax\BuckarooFee;
use Buckaroo\Magento2\Test\BaseTest;

/**
 * Amounts use a 1.2 store/base rate so that base and store values always differ.
 * Fee tax: base 2.5 / store 3.0. Fee incl. tax: base 12.5 / store 15.0.
 */
class BuckarooFeeTest extends BaseTest
{
    protected $instanceClass = BuckarooFee::class;

    /**
     * Credit memo totals before collect(): Magento has not added the fee tax yet.
     */
    private const CREDITMEMO_BEFORE = [
        'base_tax_amount'   => 17.5,
        'tax_amount'        => 21.0,
        'base_grand_total'  => 100.0,
        'grand_total'       => 120.0,
    ];

    /**
     * Fee tax amounts on the invoice (or order) being refunded.
     */
    private const FEE_SOURCE = [
        'buckaroo_fee_base_tax_amount' => 2.5,
        'buckaroo_fee_tax_amount'      => 3.0,
        'base_buckaroo_fee_incl_tax'   => 12.5,
        'buckaroo_fee_incl_tax'        => 15.0,
    ];

    /**
     * Credit memo fields that collect() fills when it refunds the fee tax.
     */
    private const CREDITMEMO_FEE_REFUNDED = [
        'buckaroo_fee_base_tax_amount' => 2.5,
        'buckaroo_fee_tax_amount'      => 3.0,
        'base_buckaroo_fee_incl_tax'   => 12.5,
        'buckaroo_fee_incl_tax'        => 15.0,
    ];

    /**
     * Credit memo fee fields when nothing is refunded.
     */
    private const CREDITMEMO_FEE_UNTOUCHED = [
        'buckaroo_fee_base_tax_amount' => null,
        'buckaroo_fee_tax_amount'      => null,
        'base_buckaroo_fee_incl_tax'   => null,
        'buckaroo_fee_incl_tax'        => null,
    ];

    /**
     * Order refunded counters after one fee tax refund (starting from nothing refunded).
     */
    private const ORDER_REFUNDED_ONCE = [
        'buckaroo_fee_base_tax_amount_refunded' => 2.5,
        'buckaroo_fee_tax_amount_refunded'      => 3.0,
        'base_buckaroo_fee_incl_tax_refunded'   => 12.5,
        'buckaroo_fee_incl_tax_refunded'        => 15.0,
    ];

    /**
     * Order refunded counters when nothing was refunded before or by collect().
     */
    private const ORDER_NOTHING_REFUNDED = [
        'buckaroo_fee_base_tax_amount_refunded' => 0.0,
        'buckaroo_fee_tax_amount_refunded'      => null,
        'base_buckaroo_fee_incl_tax_refunded'   => null,
        'buckaroo_fee_incl_tax_refunded'        => null,
    ];

    /**
     * Credit memo after collect() added the fee tax: tax 17.5 + 2.5 / 21 + 3, grand total 100 + 2.5 / 120 + 3.
     */
    private const CREDITMEMO_FEE_TAX_ADDED = [
        // Same fee fields as CREDITMEMO_FEE_REFUNDED, written out: the CI mess detector cannot parse
        // constant expressions
        'buckaroo_fee_base_tax_amount' => 2.5,
        'buckaroo_fee_tax_amount'      => 3.0,
        'base_buckaroo_fee_incl_tax'   => 12.5,
        'buckaroo_fee_incl_tax'        => 15.0,
        'base_tax_amount'  => 20.0,
        'tax_amount'       => 24.0,
        'base_grand_total' => 102.5,
        'grand_total'      => 123.0,
    ];

    /**
     * Each row: invoice data (null = no invoice), order data, expected order data, expected credit memo data.
     *
     * @return array
     */
    public static function collectProvider(): array
    {
        $orderTax = ['base_tax_amount' => 20.0, 'tax_amount' => 24.0];

        return [
            'first refund adds fee tax to totals and counters' => [
                self::FEE_SOURCE,
                $orderTax + [
                    'buckaroo_fee_base_tax_amount_invoiced' => 2.5,
                ],
                self::ORDER_REFUNDED_ONCE,
                self::CREDITMEMO_FEE_TAX_ADDED,
            ],
            'second invoice refunded after the first one' => [
                self::FEE_SOURCE,
                $orderTax + self::ORDER_REFUNDED_ONCE + ['buckaroo_fee_base_tax_amount_invoiced' => 5.0],
                [
                    'buckaroo_fee_base_tax_amount_refunded' => 5.0,
                    'buckaroo_fee_tax_amount_refunded'      => 6.0,
                    'base_buckaroo_fee_incl_tax_refunded'   => 25.0,
                    'buckaroo_fee_incl_tax_refunded'        => 30.0,
                ],
                self::CREDITMEMO_FEE_TAX_ADDED,
            ],
            'refund without invoice uses the order fee tax' => [
                null,
                $orderTax + self::FEE_SOURCE + [
                    'buckaroo_fee_base_tax_amount_invoiced' => 2.5,
                ],
                self::ORDER_REFUNDED_ONCE,
                self::CREDITMEMO_FEE_TAX_ADDED,
            ],
            'credit memo tax already complete updates counters only' => [
                self::FEE_SOURCE,
                // Order tax equals the credit memo tax: Magento already included the fee tax.
                ['base_tax_amount' => 17.5, 'tax_amount' => 21.0,
                    'buckaroo_fee_base_tax_amount_invoiced' => 2.5],
                self::ORDER_REFUNDED_ONCE,
                self::CREDITMEMO_FEE_REFUNDED + self::CREDITMEMO_BEFORE,
            ],
            'no fee tax on invoice' => [
                ['buckaroo_fee_base_tax_amount' => 0, 'buckaroo_fee_tax_amount' => 0],
                $orderTax + [
                    'buckaroo_fee_base_tax_amount_invoiced' => 2.5,
                    'buckaroo_fee_base_tax_amount_refunded' => 0.0,
                ],
                self::ORDER_NOTHING_REFUNDED,
                self::CREDITMEMO_FEE_UNTOUCHED + self::CREDITMEMO_BEFORE,
            ],
            'no fee tax invoiced' => [
                self::FEE_SOURCE,
                $orderTax + [
                    'buckaroo_fee_base_tax_amount_invoiced' => 0.0,
                    'buckaroo_fee_base_tax_amount_refunded' => 0.0,
                ],
                self::ORDER_NOTHING_REFUNDED,
                self::CREDITMEMO_FEE_UNTOUCHED + self::CREDITMEMO_BEFORE,
            ],
            'all fee tax already refunded' => [
                self::FEE_SOURCE,
                $orderTax + self::ORDER_REFUNDED_ONCE + ['buckaroo_fee_base_tax_amount_invoiced' => 2.5],
                self::ORDER_REFUNDED_ONCE,
                self::CREDITMEMO_FEE_UNTOUCHED + self::CREDITMEMO_BEFORE,
            ],
        ];
    }

    /**
     * @param array|null $invoiceData
     * @param array      $orderData
     * @param array      $expectedOrder
     * @param array      $expectedCreditmemo
     */
    #[DataProvider('collectProvider')]
    public function testCollect(
        ?array $invoiceData,
        array $orderData,
        array $expectedOrder,
        array $expectedCreditmemo
    ): void {
        // Order and invoice are partial doubles so the magic getters/setters keep real state.
        $order = $this->getFakeMock(Order::class)->onlyMethods([])->getMock();
        $order->setData($orderData);

        $invoice = null;
        if ($invoiceData !== null) {
            $invoice = $this->getFakeMock(Invoice::class)->onlyMethods([])->getMock();
            $invoice->setData($invoiceData);
        }

        $creditmemo = $this->getFakeMock(Creditmemo::class)->onlyMethods(['getOrder', 'getInvoice'])->getMock();
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->method('getInvoice')->willReturn($invoice);
        $creditmemo->setData(self::CREDITMEMO_BEFORE);

        $instance = $this->getInstance();
        $this->assertSame($instance, $instance->collect($creditmemo));

        foreach ($expectedOrder as $key => $value) {
            $this->assertSame($value, $order->getData($key), 'Order ' . $key);
        }
        foreach ($expectedCreditmemo as $key => $value) {
            $this->assertSame($value, $creditmemo->getData($key), 'Credit memo ' . $key);
        }
    }
}
