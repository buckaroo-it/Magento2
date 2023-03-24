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
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Service\Order;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var Order
     */
    protected $orderService;
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var Account
     */
    private $accountConfig;
    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @param Session $checkoutSession
     * @param Account $accountConfig
     * @param Data $helper
     * @param CartRepositoryInterface $quoteRepository
     * @param Order $orderService
     */
    public function __construct(
        Session $checkoutSession,
        Account $accountConfig,
        Data $helper,
        CartRepositoryInterface $quoteRepository,
        Order $orderService
    ) {
        $this->orderService = $orderService;
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $accountConfig;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Restore Quote and Cancel LastRealOrder
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
            if (
                $this->shouldSkipFurtherEventHandling()
                || strpos($payment->getMethod(), 'buckaroo_magento2') === false
                || in_array($payment->getMethod(), [Giftcards::CODE, Payconiq::CODE])
            ) {
                $this->helper->addDebug(__METHOD__ . '|10|');
                return;
            }

            if ($this->accountConfig->getCartKeepAlive($lastRealOrder->getStore())) {
                $this->helper->addDebug(__METHOD__ . '|20|');

                if (
                    $this->checkoutSession->getQuote()
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

                $this->helper->addDebug(__METHOD__ . '|restoreQuote|' . var_export($this->helper->getRestoreQuoteLastOrder(), true));
                $this->helper->addDebug(__METHOD__ . '|state|' . var_export($lastRealOrder->getData('state'), true));
                $this->helper->addDebug(__METHOD__ . '|status|' . var_export($lastRealOrder->getData('status'), true));
                $this->helper->addDebug(__METHOD__ . '|usesRedirect|' . var_export($payment->getMethodInstance()->usesRedirect, true));

                if (
                    $this->helper->getRestoreQuoteLastOrder()
                    && ($lastRealOrder->getData('state') === 'new')
                    && ($lastRealOrder->getData('status') === 'pending')
                    && $payment->getMethodInstance()->usesRedirect
                ) {
                    $this->helper->addDebug(__METHOD__ . '|40|');
                    $this->checkoutSession->restoreQuote();
                    $this->cancelLastOrder($lastRealOrder);
                }
            }

            $this->helper->addDebug(__METHOD__ . '|50|');
            $this->helper->setRestoreQuoteLastOrder(false);
        }

        $this->helper->addDebug(__METHOD__ . '|55|');
    }

    /**
     * Skip restore quote
     *
     * @return false
     */
    public function shouldSkipFurtherEventHandling()
    {
        return false;
    }

    /**
     * Cancel Last Order when the payment process has not been completed
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function cancelLastOrder($order)
    {
        return $this->orderService->cancel($order, $order->getStatus());
    }
}
