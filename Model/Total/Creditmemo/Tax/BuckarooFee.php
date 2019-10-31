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
namespace TIG\Buckaroo\Model\Total\Creditmemo\Tax;

class BuckarooFee extends \Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal
{
    /**
     * Collect totals for credit memo
     *
     * @param  \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $invoice = $creditmemo->getInvoice();

        $salesModel = ($invoice ? $invoice : $order);

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
