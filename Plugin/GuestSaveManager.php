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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

// @codingStandardsIgnoreStart

if (class_exists('\Onestepcheckout\Iosc\Plugin\GuestSaveManager')) {

    class GuestSaveManager extends \Onestepcheckout\Iosc\Plugin\GuestSaveManager
    {
        /**
         * @var \Magento\Quote\Model\QuoteIdMaskFactory
         */
        protected $quoteIdMaskFactory;

        /**
         * @var \Magento\Quote\Api\CartRepositoryInterface
         */
        protected $cartRepository;

        /**
         * @var BuckarooLoggerInterface
         */
        protected BuckarooLoggerInterface $logger;

        /**
         * @param \Onestepcheckout\Iosc\Model\DataManager $dataManager
         * @param \Magento\Framework\App\Request\Http $request
         * @param \Onestepcheckout\Iosc\Model\MockManager $mockManager
         * @param \Onestepcheckout\Iosc\Helper\Data $helper
         * @param \Magento\Checkout\Model\Session $checkoutSession
         * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
         * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
         * @param BuckarooLoggerInterface $logger
         */
        public function __construct(
            \Onestepcheckout\Iosc\Model\DataManager $dataManager, /** @phpstan-ignore-line */
            \Magento\Framework\App\Request\Http $request,
            \Onestepcheckout\Iosc\Model\MockManager $mockManager, /** @phpstan-ignore-line */
            \Onestepcheckout\Iosc\Helper\Data $helper, /** @phpstan-ignore-line */
            \Magento\Checkout\Model\Session $checkoutSession,
            \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
            \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
            BuckarooLoggerInterface $logger
        ) {
            $this->quoteIdMaskFactory = $quoteIdMaskFactory;
            $this->cartRepository     = $cartRepository;
            $this->logger             = $logger;
            /** @phpstan-ignore-next-line */
            parent::__construct($dataManager, $request, $mockManager, $helper, $checkoutSession);
        }

        /**
         * Set billing address if the billing address is null before save payment information and place order
         *
         * @param $parent
         * @param $cartId
         * @param $email
         * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
         * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
         * @return void
         * @throws \Magento\Framework\Exception\NoSuchEntityException
         */
        public function beforeSavePaymentInformationAndPlaceOrder(
            $parent,
            $cartId,
            $email,
            \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
            \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        ) {
            if ($billingAddress == null) {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
                $billingAddress = $this->cartRepository->getActive($quoteIdMask->getQuoteId())->getBillingAddress();
            }

            /** @phpstan-ignore-next-line */
            parent::beforeSavePaymentInformationAndPlaceOrder(
                $parent,
                $cartId,
                $email,
                $paymentMethod,
                $billingAddress
            );
        }

        /**
         * Set billing address if the billing address is null before save payment information
         *
         * @param $parent
         * @param $cartId
         * @param $email
         * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
         * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
         * @return void
         * @throws \Magento\Framework\Exception\NoSuchEntityException
         */
        public function beforeSavePaymentInformation(
            $parent,
            $cartId,
            $email,
            \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
            \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
        ) {
            if ($billingAddress == null) {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
                $billingAddress = $this->cartRepository->getActive($quoteIdMask->getQuoteId())->getBillingAddress();
            }

            /** @phpstan-ignore-next-line */
            parent::beforeSavePaymentInformation($parent, $cartId, $email, $paymentMethod, $billingAddress);
        }
    }

} else {
    class GuestSaveManager
    {

    }
}

// @codingStandardsIgnoreEnd

