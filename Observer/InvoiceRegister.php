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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;

class InvoiceRegister implements ObserverInterface
{
    /**
     * Set invoiced buckaroo fee to order after invoice register
     *
     * @param  Observer $observer
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
