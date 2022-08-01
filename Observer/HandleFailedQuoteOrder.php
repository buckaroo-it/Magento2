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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\OrderManagementInterface;
use Buckaroo\Magento2\Model\Session as BuckarooSession;

class HandleFailedQuoteOrder implements \Magento\Framework\Event\ObserverInterface
{
    protected $buckarooSession;
    protected $logging;
    protected $moduleManager;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    public function __construct(
        BuckarooSession $buckarooSession,
        Log $logging,
        Manager $moduleManager,
        OrderManagementInterface $orderManagement
    ) {
        $this->buckarooSession = $buckarooSession;
        $this->logging = $logging;
        $this->moduleManager = $moduleManager;
        $this->orderManagement = $orderManagement;
    }

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
            if (in_array(
                $payment->getMethodInstance()->getCode(),
                ['buckaroo_magento2_afterpay','buckaroo_magento2_afterpay2','buckaroo_magento2_klarnakp']
            )) {
                try {
                    $order->addStatusHistoryComment('Buckaroo: failed to authorize an order', false);
                    $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                    $payment->save();
                    //phpcs:ignore: Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
                } catch (\Exception $e) {

                }
            }

            try {
                $this->logging->addDebug(__METHOD__ . '|1|');
                if ($this->moduleManager->isEnabled('Magento_Inventory')) {
                    $this->logging->addDebug(__METHOD__ . '|5|');
                    $this->buckarooSession->setData('flagHandleFailedQuote', 1);
                }
                $this->orderManagement->cancel($order->getId());
                //phpcs:ignore: Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Exception $e) {

            }
            $this->buckarooSession->setData('flagHandleFailedQuote', 0);
        }
    }
}
