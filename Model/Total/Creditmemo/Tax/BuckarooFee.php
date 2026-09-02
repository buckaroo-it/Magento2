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
namespace Buckaroo\Magento2\Model\Total\Creditmemo\Tax;

class BuckarooFee extends \Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal
{
    /**
     * Collect totals for credit memo
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $invoice = $creditmemo->getInvoice();

        $salesModel = ($invoice ?: $order);

        if ($salesModel->getBuckarooFeeBaseTaxAmount()
            && $order->getBuckarooFeeBaseTaxAmountInvoiced() > $order->getBuckarooFeeBaseTaxAmountRefunded()
        ) {
            $baseBuckarooFeeTax = $salesModel->getBuckarooFeeBaseTaxAmount();
            $buckarooFeeTax = $salesModel->getBuckarooFeeTaxAmount();

            $order->setBuckarooFeeBaseTaxAmountRefunded(
                $order->getBuckarooFeeBaseTaxAmountRefunded() +  $baseBuckarooFeeTax
            );
            $order->setBuckarooFeeTaxAmountRefunded($order->getBuckarooFeeTaxAmountRefunded() + $buckarooFeeTax);

            $creditmemo->setBuckarooFeeBaseTaxAmount($baseBuckarooFeeTax);
            $creditmemo->setBuckarooFeeTaxAmount($buckarooFeeTax);

            $buckarooFeeInclTax = $salesModel->getBuckarooFeeInclTax();
            $baseBuckarooFeeInclTax = $salesModel->getBaseBuckarooFeeInclTax();

            $order->setBuckarooFeeInclTaxRefunded($order->getBuckarooFeeInclTaxRefunded() + $buckarooFeeInclTax);
            $order->setBaseBuckarooFeeInclTaxRefunded(
                $order->getBaseBuckarooFeeInclTaxRefunded() + $baseBuckarooFeeInclTax
            );

            $creditmemo->setBuckarooFeeInclTax($buckarooFeeInclTax);
            $creditmemo->setBaseBuckarooFeeInclTax($baseBuckarooFeeInclTax);

            // Partial refunds are OK, magento did not add the payment fee tax yet so we do it
            // Full refunds there is double payment fee tax, because magento already added the tax
            // We check if the tax is not more than it should be..
            if ($creditmemo->getBaseTaxAmount() < $order->getBaseTaxAmount()) {
                $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseBuckarooFeeTax);
                $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $buckarooFeeTax);

                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseBuckarooFeeTax);
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $buckarooFeeTax);
            }
        }

        return $this;
    }
}
