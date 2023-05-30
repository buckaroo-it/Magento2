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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\Store;

class ShippingMethodManagement
{
    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var Account
     */
    private Account $accountConfig;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Account $accountConfig
     * @param Data $helper
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Session $checkoutSession,
        CustomerSession $customerSession,
        Account $accountConfig,
        Data $helper,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->accountConfig   = $accountConfig;
        $this->helper          = $helper;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Ensures that the shipping address is loaded and shipping rates are collected.
     *
     * @param int $cartId
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function beforeGet(\Magento\Quote\Model\ShippingMethodManagement $subject, int $cartId)
    {
        if (($lastRealOrder = $this->checkoutSession->getLastRealOrder())
            && ($payment = $lastRealOrder->getPayment())
        ) {
            if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
                return;
            }

            $order = $payment->getOrder();

            $this->helper->addDebug(__METHOD__ . '|1|');
            if ($this->accountConfig->getCartKeepAlive($order->getStore())
                && $this->isNeedRecreate($order->getStore())
            ) {
                $this->helper->addDebug(__METHOD__ . '|2|');
                if ($this->checkoutSession->getQuote()
                    && $this->checkoutSession->getQuote()->getId()
                    && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
                ) {
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
     * Function that is used by external plugins
     *
     * @param Store $store
     * @return false
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isNeedRecreate($store): bool
    {
        return false;
    }
}
