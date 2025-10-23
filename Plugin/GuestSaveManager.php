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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\Log;

class GuestSaveManager
{
    protected $quoteIdMaskFactory;
    protected $cartRepository;
    protected Log $logger;

    public function __construct(
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        Log $logger
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    public function beforeSavePaymentInformationAndPlaceOrder(
        \Onestepcheckout\Iosc\Plugin\GuestSaveManager $subject,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($billingAddress == null) {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $billingAddress = $this->cartRepository->getActive($quoteIdMask->getQuoteId())->getBillingAddress();
        }

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }

    public function beforeSavePaymentInformation(
        \Onestepcheckout\Iosc\Plugin\GuestSaveManager $subject,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($billingAddress == null) {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $billingAddress = $this->cartRepository->getActive($quoteIdMask->getQuoteId())->getBillingAddress();
        }

        return [$cartId, $email, $paymentMethod, $billingAddress];
    }
}
