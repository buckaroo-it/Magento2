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

namespace TIG\Buckaroo\Observer;

class HandleFailedQuoteOrder implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /* @var $order \Magento\Sales\Model\Order */
        $order = $observer->getEvent()->getOrder();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var $quote \Magento\Quote\Model\Quote $quote
         */
        $quote = $observer->getEvent()->getQuote();


        if ($order->canCancel()) {
            //$this->logging->addDebug('Buckaroo push failed : '.$message.' : Cancel order.');

            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/AbstractMethod.php:880
            $payment = $order->getPayment();
            if ($payment->getMethodInstance()->getCode() == 'tig_buckaroo_afterpay'
                || $payment->getMethodInstance()->getCode() == 'tig_buckaroo_afterpay2'
                || $payment->getMethodInstance()->getCode() == 'tig_buckaroo_klarna'
            ) {
                $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                $payment->save();
            }

            $order->cancel()->save();
        }

    }
}
