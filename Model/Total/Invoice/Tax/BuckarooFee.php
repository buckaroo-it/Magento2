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
namespace Buckaroo\Magento2\Model\Total\Invoice\Tax;

class BuckarooFee extends \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
{
    /**
     * Collect buckaroo fee tax totals
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $order = $invoice->getOrder();

        $buckarooFeeTaxAmountLeft = $order->getBuckarooFeeTaxAmount() - $order->getBuckarooFeeTaxAmountInvoiced();
        $baseBuckarooFeeTaxAmountLeft = $order->getBuckarooFeeBaseTaxAmount()
            - $order->getBuckarooFeeBaseTaxAmountInvoiced();

        $buckarooFeeInclTaxLeft = $order->getBuckarooFeeInclTax() - $order->getBuckarooFeeInclTaxInvoiced();
        $baseBuckarooFeeInclTaxLeft = $order->getBaseBuckarooFeeInclTax() - $order->getBaseBuckarooFeeInclTaxInvoiced();

        if ($order->getBuckarooFeeBaseTaxAmount() && $baseBuckarooFeeTaxAmountLeft > 0) {
            if ($baseBuckarooFeeTaxAmountLeft < $invoice->getBaseGrandTotal()) {
                $invoice->setGrandTotal($invoice->getGrandTotal() + $buckarooFeeTaxAmountLeft);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseBuckarooFeeTaxAmountLeft);
                $invoice->setTaxAmount($invoice->getTaxAmount() + $buckarooFeeTaxAmountLeft);
                $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseBuckarooFeeTaxAmountLeft);
            } else {
                // Fee tax exceeds what is left on this invoice: consume the grand total
                // and clamp the recorded fee tax; the invoice tax already contains this
                // amount, so it must not be increased again
                $buckarooFeeTaxAmountLeft = $invoice->getTaxAmount();
                $baseBuckarooFeeTaxAmountLeft = $invoice->getBaseTaxAmount();

                $invoice->setGrandTotal(0);
                $invoice->setBaseGrandTotal(0);
            }

            $invoice->setBuckarooFeeTaxAmount($buckarooFeeTaxAmountLeft);
            $invoice->setBuckarooFeeBaseTaxAmount($baseBuckarooFeeTaxAmountLeft);

            $invoice->setBuckarooFeeInclTax($buckarooFeeInclTaxLeft);
            $invoice->setBaseBuckarooFeeInclTax($baseBuckarooFeeInclTaxLeft);
        }

        return $this;
    }
}
