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

// @codingStandardsIgnoreStart

if (class_exists('\Onestepcheckout\Iosc\Plugin\GuestSaveManager')) {

    class GuestSaveManager extends \Onestepcheckout\Iosc\Plugin\GuestSaveManager
    {
        protected $quoteIdMaskFactory;

        protected $cartRepository;

        protected $logger;

        protected \Onestepcheckout\Iosc\Model\DataManager $dataManager;

        protected \Magento\Framework\App\Request\Http $request;

        protected \Onestepcheckout\Iosc\Model\MockManager $mockManager;

        protected \Onestepcheckout\Iosc\Helper\Data $helper;

        protected \Magento\Checkout\Model\Session $checkoutSession;


        public function __construct(
            \Onestepcheckout\Iosc\Model\DataManager $dataManager,
            \Magento\Framework\App\Request\Http $request,
            \Onestepcheckout\Iosc\Model\MockManager $mockManager,
            \Onestepcheckout\Iosc\Helper\Data $helper,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
            \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
            Log $logger
        ) {
            $this->dataManager        = $dataManager;
            $this->request            = $request;
            $this->mockManager        = $mockManager;
            $this->helper             = $helper;
            $this->checkoutSession    = $checkoutSession;
            $this->quoteIdMaskFactory = $quoteIdMaskFactory;
            $this->cartRepository     = $cartRepository;
            $this->logger             = $logger;
            parent::__construct($dataManager, $request, $mockManager, $helper, $checkoutSession);
        }

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
            
            parent::beforeSavePaymentInformationAndPlaceOrder(
                $parent,
                $cartId,
                $email,
                $paymentMethod,
                $billingAddress
            );
        }

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

            parent::beforeSavePaymentInformation($parent, $cartId, $email, $paymentMethod, $billingAddress);
        }
    }

} else {
    class GuestSaveManager
    {

    }
}

// @codingStandardsIgnoreEnd

