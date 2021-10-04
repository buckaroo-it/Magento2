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
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\UrlInterface;
use Magento\GiftMessage\Model\GiftMessageManager;
use Magento\GiftMessage\Model\Message;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteAddressValidator;
use Mageplaza\Osc\Helper\Item as OscHelper;
use Mageplaza\Osc\Model\OscDetailsFactory;
use Psr\Log\LoggerInterface;

// @codingStandardsIgnoreStart

if (class_exists('\Mageplaza\Osc\Model\CheckoutManagement')) {

    class CheckoutManagement extends \Mageplaza\Osc\Model\CheckoutManagement
    {
        /** * @var CartRepositoryInterface */
        protected $cartRepository;
        /** * @var OscDetailsFactory */
        protected $oscDetailsFactory;
        /** * @var ShippingMethodManagementInterface */
        protected $shippingMethodManagement;
        /** * @var PaymentMethodManagementInterface */
        protected $paymentMethodManagement;
        /** * @var CartTotalRepositoryInterface */
        protected $cartTotalsRepository;
        /** * Url Builder * * @var UrlInterface */
        protected $_urlBuilder;
        /** * Checkout session * * @var Session */
        protected $checkoutSession;
        /** * @var ShippingInformationManagementInterface */
        protected $shippingInformationManagement;
        /** * @var OscHelper */
        protected $oscHelper;
        /** * @var Message */
        protected $giftMessage;
        /** * @var GiftMessageManager */
        protected $giftMessageManagement;
        /** * @var CustomerSession */
        protected $_customerSession;
        /** * @var TotalsCollector */
        protected $_totalsCollector;
        /** * @var AddressInterface */
        protected $_addressInterface;
        /** * @var ShippingMethodConverter */
        protected $_shippingMethodConverter;
        /** * @var QuoteAddressValidator */
        protected $addressValidator;
        /** * @var LoggerInterface */
        private $logger;

        public function __construct(
            CartRepositoryInterface $cartRepository,
            OscDetailsFactory $oscDetailsFactory,
            ShippingMethodManagementInterface $shippingMethodManagement,
            PaymentMethodManagementInterface $paymentMethodManagement,
            CartTotalRepositoryInterface $cartTotalsRepository,
            UrlInterface $urlBuilder,
            Session $checkoutSession,
            ShippingInformationManagementInterface $shippingInformationManagement,
            OscHelper $oscHelper,
            Message $giftMessage,
            GiftMessageManager $giftMessageManager,
            customerSession $customerSession,
            TotalsCollector $totalsCollector,
            AddressInterface $addressInterface,
            ShippingMethodConverter $shippingMethodConverter,
            QuoteAddressValidator $quoteAddressValidator,
            LoggerInterface $logger
        ) {
            $this->cartRepository                = $cartRepository;
            $this->oscDetailsFactory             = $oscDetailsFactory;
            $this->shippingMethodManagement      = $shippingMethodManagement;
            $this->paymentMethodManagement       = $paymentMethodManagement;
            $this->cartTotalsRepository          = $cartTotalsRepository;
            $this->_urlBuilder                   = $urlBuilder;
            $this->checkoutSession               = $checkoutSession;
            $this->shippingInformationManagement = $shippingInformationManagement;
            $this->oscHelper                     = $oscHelper;
            $this->giftMessage                   = $giftMessage;
            $this->giftMessageManagement         = $giftMessageManager;
            $this->_customerSession              = $customerSession;
            $this->_totalsCollector              = $totalsCollector;
            $this->_addressInterface             = $addressInterface;
            $this->_shippingMethodConverter      = $shippingMethodConverter;
            $this->addressValidator              = $quoteAddressValidator;
            $this->logger                        = $logger;

            parent::__construct(
                $cartRepository,
                $oscDetailsFactory,
                $shippingMethodManagement,
                $paymentMethodManagement,
                $cartTotalsRepository,
                $urlBuilder,
                $checkoutSession,
                $shippingInformationManagement,
                $oscHelper,
                $giftMessage,
                $giftMessageManager,
                $customerSession,
                $totalsCollector,
                $addressInterface,
                $shippingMethodConverter,
                $quoteAddressValidator,
                $logger
            );
        }

        public function updateItemQty($cartId, $itemId, $itemQty)
        {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getBaseBuckarooAlreadyPaid() > 0) {
                throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
            }

            parent::updateItemQty($cartId, $itemId, $itemQty);
        }

        public function removeItemById($cartId, $itemId)
        {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getBaseBuckarooAlreadyPaid() > 0) {
                throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
            }
            parent::removeItemById($cartId, $itemId);
        }
    }

} else {
    class CheckoutManagement
    {
    }
}

// @codingStandardsIgnoreEnd
