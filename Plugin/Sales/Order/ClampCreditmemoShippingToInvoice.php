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

namespace Buckaroo\Magento2\Plugin\Sales\Order;

use Magento\Sales\Model\Order\Creditmemo;

/**
 * Keep a credit memo's refundable shipping within what its own invoice charged.
 *
 * CreditmemoFactory::getShippingAmount() clamps to the invoice only when shipping prices are
 * displayed EXCLUDING tax. The including-tax branch works off the ORDER, so on an order invoiced
 * per shipment - where Magento settles all shipping on the first invoice - a memo for any later
 * invoice is seeded with shipping that invoice never charged, and goes negative once some shipping
 * has been refunded.
 *
 * Creditmemo\Total\Discount runs before Total\Shipping and feeds that figure into its
 * shipping-discount branch, so the memo's header discount comes out wrong while its item lines
 * stay correct - which overstates total_refunded and blocks the next refund.
 */
class ClampCreditmemoShippingToInvoice
{
    /**
     * Clamp every shipping field before any total collector reads one.
     *
     * @param Creditmemo $creditmemo
     *
     * @return null
     */
    public function beforeCollectTotals(Creditmemo $creditmemo)
    {
        $invoice = $creditmemo->getInvoice();

        if ($invoice === null || !$invoice->getId()) {
            return null;
        }

        // Every field has to be clamped, not just the amount: Total\Discount treats a shipping
        // amount of 0 as "not set" and falls back to base_shipping_incl_tax, which toCreditmemo()
        // seeded from the order, so zeroing one field moves the wrong number one field along.
        $fields = [
            'BaseShippingAmount'     => (float)$invoice->getBaseShippingAmount(),
            'ShippingAmount'         => (float)$invoice->getShippingAmount(),
            'BaseShippingInclTax'    => (float)$invoice->getBaseShippingInclTax(),
            'ShippingInclTax'        => (float)$invoice->getShippingInclTax(),
            'BaseShippingTaxAmount'  => (float)$invoice->getBaseShippingTaxAmount(),
            'ShippingTaxAmount'      => (float)$invoice->getShippingTaxAmount(),
        ];

        foreach ($fields as $field => $invoiced) {
            $current = (float)$creditmemo->getDataUsingMethod($this->toKey($field));
            $creditmemo->setDataUsingMethod($this->toKey($field), $this->clamp($current, $invoiced));
        }

        return null;
    }

    /**
     * Never more than the invoice charged, never below zero.
     *
     * @param float $amount
     * @param float $invoiced
     *
     * @return float
     */
    private function clamp(float $amount, float $invoiced): float
    {
        return max(0.0, min($amount, max(0.0, $invoiced)));
    }

    /**
     * Turn a getter suffix into the data key the credit memo stores it under.
     *
     * @param string $field
     *
     * @return string
     */
    private function toKey(string $field): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
    }
}
