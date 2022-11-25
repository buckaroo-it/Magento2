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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Payconiq;

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    private $accountConfig;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Buckaroo\Magento2\Helper\Data
     */
    private $helper;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
     * @param \Buckaroo\Magento2\Helper\Data $helper
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession        = $checkoutSession;
        $this->accountConfig          = $accountConfig;
        $this->helper                 = $helper;
        $this->quoteRepository        = $quoteRepository;
    }

    /**
     * Restore Quote
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->helper->addDebug(__METHOD__ . '|1|');

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($payment = $lastRealOrder->getPayment()) {
            if ($this->shouldSkipFurtherEventHandling()) {
                $this->helper->addDebug(__METHOD__ . '|10|');
                return;
            }
            if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
                return;
            }
            if (in_array($payment->getMethod(), [Giftcards::CODE, Payconiq::CODE])) {
                return true;
            }
            $order = $payment->getOrder();

            if ($this->accountConfig->getCartKeepAlive($order->getStore())) {
                $this->helper->addDebug(__METHOD__ . '|20|');

                if ($this->checkoutSession->getQuote()
                    && $this->checkoutSession->getQuote()->getId()
                    && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
                ) {
                    $this->helper->addDebug(__METHOD__ . '|25|');
                    if ($shippingAddress = $quote->getShippingAddress()) {
                        if (!$shippingAddress->getShippingMethod()) {
                            $this->helper->addDebug(__METHOD__ . '|35|');
                            $shippingAddress->load($shippingAddress->getAddressId());
                        }
                    }
                }

                if ($this->helper->getRestoreQuoteLastOrder()
                    && ($lastRealOrder->getData('state') === 'new')
                    && ($lastRealOrder->getData('status') === 'pending')
                    && $payment->getMethodInstance()->usesRedirect
                ) {
                    $this->helper->addDebug(__METHOD__ . '|40|');
                    $this->checkoutSession->restoreQuote();
                }
            }
            $this->helper->addDebug(__METHOD__ . '|50|');
            $this->helper->setRestoreQuoteLastOrder(false);
        }
        $this->helper->addDebug(__METHOD__ . '|55|');
        return true;
    }

    public function shouldSkipFurtherEventHandling()
    {
        return true;
    }
}
