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

namespace Buckaroo\Magento2\Model\Total\Invoice;

class BuckarooFee extends \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
{
    /**
     * Collect Buckaroo fee total for invoice
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $order = $invoice->getOrder();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $buckarooFeeLeft = $order->getBuckarooFee() - $order->getBuckarooFeeInvoiced();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $baseBuckarooFeeLeft = $order->getBaseBuckarooFee() - $order->getBaseBuckarooFeeInvoiced();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($order->getBaseBuckarooFee() && $baseBuckarooFeeLeft > 0) {
            if ($baseBuckarooFeeLeft < $invoice->getBaseGrandTotal()) {
                $invoice->setGrandTotal($invoice->getGrandTotal() + $buckarooFeeLeft);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseBuckarooFeeLeft);
            } else {
                $buckarooFeeLeft = $invoice->getGrandTotal();
                $baseBuckarooFeeLeft = $invoice->getBaseGrandTotal();

                $invoice->setGrandTotal(0);
                $invoice->setBaseGrandTotal(0);
            }

            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $invoice->setBuckarooFee($buckarooFeeLeft);
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $invoice->setBaseBuckarooFee($baseBuckarooFeeLeft);
        }

        return $this;
    }
}
