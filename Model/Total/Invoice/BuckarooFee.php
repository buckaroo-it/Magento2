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

namespace Buckaroo\Magento2\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class BuckarooFee extends AbstractTotal
{
    /**
     * Collect Buckaroo fee total for invoice
     *
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice): BuckarooFee
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
