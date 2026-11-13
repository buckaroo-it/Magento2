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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class SetBuckarooFee implements ObserverInterface
{
    /**
     * Set Buckaroo fee on sales_model_service_quote_submit_before event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var $quote Quote $quote
         */
        $quote = $observer->getEvent()->getQuote();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($quote->getBaseBuckarooFee() > 0) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFee($quote->getBuckarooFee());
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBaseBuckarooFee($quote->getBaseBuckarooFee());
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeTaxAmount($quote->getBuckarooFeeTaxAmount());
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeBaseTaxAmount($quote->getBuckarooFeeBaseTaxAmount());
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBuckarooFeeInclTax($quote->getBuckarooFeeInclTax());
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $order->setBaseBuckarooFeeInclTax($quote->getBaseBuckarooFeeInclTax());
        }
    }
}
