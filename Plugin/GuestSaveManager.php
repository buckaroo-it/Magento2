<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Onestepcheckout\Iosc\Helper\Data;
use Onestepcheckout\Iosc\Model\DataManager;
use Onestepcheckout\Iosc\Model\MockManager;

// @codingStandardsIgnoreStart

if (class_exists('\Onestepcheckout\Iosc\Plugin\GuestSaveManager')) {

    class GuestSaveManager extends \Onestepcheckout\Iosc\Plugin\GuestSaveManager
    {
        /**
         * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
         */
        protected $maskedQuoteIdToQuoteId;

        /**
         * @var \Magento\Quote\Api\CartRepositoryInterface
         */
        protected $cartRepository;

        /**
         * @var BuckarooLoggerInterface
         */
        protected $logger;

        /**
         * @var \Onestepcheckout\Iosc\Model\DataManager
         */
        protected $dataManager;
        /**
         * @var \Magento\Framework\App\Request\Http
         */
        protected $request;
        /**
         * @var \Onestepcheckout\Iosc\Model\MockManager
         */
        protected $mockManager;
        /**
         * @var \Onestepcheckout\Iosc\Helper\Data
         */
        protected $helper;
        /**
         * @var \Magento\Checkout\Model\Session
         */
        protected $checkoutSession;

        /**
         * @param DataManager $dataManager
         * @param Http $request
         * @param MockManager $mockManager
         * @param Data $helper
         * @param Session $checkoutSession
         * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
         * @param CartRepositoryInterface $cartRepository
         * @param BuckarooLoggerInterface $logger
         */
        public function __construct(
            \Onestepcheckout\Iosc\Model\DataManager $dataManager, /** @phpstan-ignore-line */
            \Magento\Framework\App\Request\Http $request,
            \Onestepcheckout\Iosc\Model\MockManager $mockManager, /** @phpstan-ignore-line */
            \Onestepcheckout\Iosc\Helper\Data $helper, /** @phpstan-ignore-line */
            \Magento\Checkout\Model\Session $checkoutSession,
            MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
            \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
            BuckarooLoggerInterface $logger
        ) {
            $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
            $this->cartRepository     = $cartRepository;
            $this->logger             = $logger;
            /** @phpstan-ignore-next-line */
            parent::__construct($dataManager, $request, $mockManager, $helper, $checkoutSession);
        }

        /**
         * Set billing address if the billing address is null before save payment information and place order
         *
         * @param                       $parent
         * @param                       $cartId
         * @param                       $email
         * @param PaymentInterface      $paymentMethod
         * @param AddressInterface|null $billingAddress
         *
         * @throws \Magento\Framework\Exception\NoSuchEntityException
         */
        public function beforeSavePaymentInformationAndPlaceOrder(
            $parent,
            $cartId,
            $email,
            PaymentInterface $paymentMethod,
            ?AddressInterface $billingAddress = null
        ) {
            if ($billingAddress == null) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
                $billingAddress = $this->cartRepository->getActive($quoteId)->getBillingAddress();
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
         * @param                       $parent
         * @param                       $cartId
         * @param                       $email
         * @param PaymentInterface      $paymentMethod
         * @param AddressInterface|null $billingAddress
         *
         * @throws \Magento\Framework\Exception\NoSuchEntityException
         */
        public function beforeSavePaymentInformation(
            $parent,
            $cartId,
            $email,
            PaymentInterface $paymentMethod,
            ?AddressInterface $billingAddress = null
        ) {
            if ($billingAddress == null) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
                $billingAddress = $this->cartRepository->getActive($quoteId)->getBillingAddress();
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

