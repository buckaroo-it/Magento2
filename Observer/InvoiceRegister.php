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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;

class InvoiceRegister implements ObserverInterface
{
    /**
     * Set invoiced buckaroo fee to order after invoice register
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /* @var $invoice Invoice */
        $invoice = $observer->getEvent()->getInvoice();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($invoice->getBaseBuckarooFee()) {
            $order = $invoice->getOrder();
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeInvoiced(
                $order->getBuckarooFeeInvoiced() + $invoice->getBuckarooFee()
            );
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBaseBuckarooFeeInvoiced(
                $order->getBaseBuckarooFeeInvoiced() + $invoice->getBaseBuckarooFee()
            );
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeTaxAmountInvoiced(
                $order->getBuckarooFeeTaxAmountInvoiced() + $invoice->getBuckarooFeeTaxAmount()
            );
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeBaseTaxAmountInvoiced(
                $order->getBuckarooFeeBaseTaxAmountInvoiced() + $invoice->getBuckarooFeeBaseTaxAmount()
            );

            $order->setBuckarooFeeInclTaxInvoiced(
                $order->getBuckarooFeeInclTaxInvoiced() + $invoice->getBuckarooFeeInclTax()
            );

            $order->setBaseBuckarooFeeInclTaxInvoiced(
                $order->getBaseBuckarooFeeInclTaxInvoiced() + $invoice->getBaseBuckarooFeeInclTax()
            );
        }

        return $this;
    }
}
