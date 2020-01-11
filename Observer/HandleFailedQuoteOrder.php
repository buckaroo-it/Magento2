<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) 2015 Total Internet Group B.V. (http://www.tig.nl)
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
