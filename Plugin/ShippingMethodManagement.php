<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License.
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class ShippingMethodManagement
{
    private Session $checkoutSession;
    private Account $accountConfig;
    private Data $helper;
    private CartRepositoryInterface $quoteRepository;

    /**
     * @param Session                 $checkoutSession
     * @param Account                 $accountConfig
     * @param Data                    $helper
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Session $checkoutSession,
        Account $accountConfig,
        Data $helper,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig   = $accountConfig;
        $this->helper          = $helper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Before plugin for get() method.
     *
     * @param  mixed                                    $cartId
     * @throws NoSuchEntityException|LocalizedException
     */
    public function beforeGet($cartId): void
    {
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($lastRealOrder && ($payment = $lastRealOrder->getPayment())) {
            if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
                return;
            }

            $order = $payment->getOrder();
            $this->helper->addDebug(__METHOD__ . '|1|');

            if ($this->accountConfig->getCartKeepAlive($order->getStore())
                && $this->isNeedRecreate($order->getStore())
            ) {
                $this->helper->addDebug(__METHOD__ . '|2|');
                $quote = $this->checkoutSession->getQuote();
                if ($quote && $quote->getId()) {
                    $quote = $this->quoteRepository->getActive((int)$quote->getId());
                    $this->helper->addDebug(__METHOD__ . '|3|');
                    if ($shippingAddress = $quote->getShippingAddress()) {
                        $this->helper->addDebug(__METHOD__ . '|4|');
                        if (!$shippingAddress->getShippingMethod()) {
                            $this->helper->addDebug(__METHOD__ . '|5|');
                            $shippingAddress->load($shippingAddress->getAddressId());
                        }
                        $shippingAddress->setCollectShippingRates(true);
                    }
                }
            }
        }
    }

    /**
     * Determine if the cart needs to be recreated.
     *
     * @param  mixed $store
     * @return bool
     */
    public function isNeedRecreate($store): bool
    {
        // Currently always return false; adjust if your business logic requires recreation.
        return false;
    }
}
