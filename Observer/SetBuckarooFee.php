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
     * @return void
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
