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
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
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
    private CustomerSession $customerSession;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    /**
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Account $accountConfig
     * @param BuckarooLoggerInterface $logger
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Session $checkoutSession,
        CustomerSession $customerSession,
        Account $accountConfig,
        BuckarooLoggerInterface $logger,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->accountConfig   = $accountConfig;
        $this->logger          = $logger;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Ensures that the shipping address is loaded and shipping rates are collected.
     *
     * @param \Magento\Quote\Model\ShippingMethodManagement $subject
     * @param int $cartId
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
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

            $this->logger->addDebug(sprintf(
                '[SET_SHIPPING] | [Plugin] | [%s:%s] - START - Ensures that the shipping address is loaded '
                . ' and shipping rates are collected. | lastRealOrder: %s',
                __METHOD__, __LINE__,
                $lastRealOrder->getIncrementId(),
            ));

            if ($this->accountConfig->getCartKeepAlive($order->getStore())
                && $this->isNeedRecreate($order->getStore())
            ) {
                if ($this->checkoutSession->getQuote()
                    && $this->checkoutSession->getQuote()->getId()
                    && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
                ) {
                    if ($shippingAddress = $quote->getShippingAddress()) {
                        if (!$shippingAddress->getShippingMethod()) {
                            $this->logger->addDebug(sprintf(
                                '[SET_SHIPPING] | [Plugin] | [%s:%s] - SET SHIPPING ADDRESS - Ensures that '
                                . 'the shipping address is loaded. | lastRealOrder: %s | shippingAddressId: %s',
                                __METHOD__, __LINE__,
                                $lastRealOrder->getIncrementId(),
                                $shippingAddress->getAddressId()
                            ));
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
