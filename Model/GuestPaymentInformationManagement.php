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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\GuestPaymentInformationManagementInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\GuestPaymentInformationManagement as MagentoGuestPaymentInformationManagement;

// @codingStandardsIgnoreStart
class GuestPaymentInformationManagement implements
    GuestPaymentInformationManagementInterface
    // @codingStandardsIgnoreEnd
{
    /**
     * @var Registry
     */
    protected $registry = null;

    /**
     * @var Log
     */
    protected $logging = null;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var MagentoGuestPaymentInformationManagement
     */
    protected $guestPaymentInformationManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param Registry                                 $registry
     * @param Log                                      $logging
     * @param Factory                                  $configProviderMethodFactory
     * @param OrderRepositoryInterface                 $orderRepository
     * @param MagentoGuestPaymentInformationManagement $guestPaymentInformationManagement
     * @param QuoteIdMaskFactory                       $quoteIdMaskFactory
     * @param CartRepositoryInterface                  $cartRepository
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Registry $registry,
        Log $logging,
        Factory $configProviderMethodFactory,
        OrderRepositoryInterface $orderRepository,
        MagentoGuestPaymentInformationManagement $guestPaymentInformationManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository
    ) {
        $this->registry = $registry;
        $this->logging = $logging;
        $this->configProviderMethodFactory  = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * @param  int                   $cartId
     * @param  string                $email
     * @param  PaymentInterface      $paymentMethod
     * @param  AddressInterface|null $billingAddress
     * @throws LocalizedException
     * @return string
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {

        $this->checkSpecificCountry($paymentMethod, $billingAddress);

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        $quote->reserveOrderId();

        $orderId = $this->guestPaymentInformationManagement->savePaymentInformationAndPlaceOrder($cartId, $email, $paymentMethod, $billingAddress);

        $this->logging->debug('-[RESULT]----------------------------------------');
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $this->logging->debug(print_r($this->registry->registry('buckaroo_response'), true));
        $this->logging->debug('-------------------------------------------------');

        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            return json_encode([
                "buckaroo_response" => $this->registry->registry('buckaroo_response')[0],
                "order_id" => $orderId,
            ]);
        }
        return json_encode([
            "limitReachedMessage" => $this->getLimitReachedMessage($orderId),
            "order_number" => $this->getOrderIncrementId($orderId),
        ]);
    }
    /**
     * Get limit reach message from payment object
     *
     * @param int $orderId
     *
     * @return string|null
     */
    private function getLimitReachedMessage($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        if ($order->getEntityId() !== null && $order->getPayment() !== null) {
            return $order->getPayment()->getAdditionalInformation(AbstractMethod::PAYMENT_ATTEMPTS_REACHED_MESSAGE);
        }
        return null;
    }

    protected function getOrderIncrementId($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getIncrementId();
    }

    /**
     * @param                     $paymentMethod
     * @param                     $billingAddress
     * @throws LocalizedException
     */
    public function checkSpecificCountry($paymentMethod, $billingAddress)
    {
        $paymentMethodCode = $this->normalizePaymentMethodCode($paymentMethod->getMethod());

        $configAllowSpecific = $this->configProviderMethodFactory->get($paymentMethodCode)->getAllowSpecific();

        if ($configAllowSpecific == 1) {
            $countryId = ($billingAddress === null) ? null : $billingAddress->getCountryId();
            $configSpecificCountry = $this->configProviderMethodFactory->get($paymentMethodCode)->getSpecificCountry();

            if (!in_array($countryId, $configSpecificCountry)) {
                throw new LocalizedException(
                    __('The requested Payment Method is not available for the given billing country.')
                );
            }
        }
    }

    /**
     * @param  string $methodCode
     * @return string
     */
    public function normalizePaymentMethodCode($methodCode = '')
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $methodCode));
    }
}
