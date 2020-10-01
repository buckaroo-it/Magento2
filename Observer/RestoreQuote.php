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

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{   
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $accountConfig;

    /**
     * @param \Magento\Checkout\Model\Session\Proxy                 $checkoutSession
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account         $accountConfig
     */
    public function __construct(
        \Magento\Checkout\Model\Session\Proxy $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig    = $accountConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($payment = $lastRealOrder->getPayment()) {
            if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
                return;
            }
            $order = $payment->getOrder();

            if($this->accountConfig->getCartKeepAlive($order->getStore())){
                if(!$this->checkoutSession->getPaymentEnded()){
                    $this->checkoutSession->restoreQuote();
                }
            }
        }
        return true;
    }

}
