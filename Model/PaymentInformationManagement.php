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

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Model\PaymentInformationManagement as MagentoPaymentInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

// @codingStandardsIgnoreStart
class PaymentInformationManagement implements
    PaymentInformationManagementInterface
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
     * @var MagentoPaymentInformationManagement
     */
    protected $paymentInformationManagement;

    /**
     * @param Registry                            $registry
     * @param Log                                 $logging
     * @param Factory                             $configProviderMethodFactory
     * @param OrderRepositoryInterface            $orderRepository
     * @param MagentoPaymentInformationManagement $paymentInformationManagement
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Registry $registry,
        Log                      $logging,
        Factory $configProviderMethodFactory,
        OrderRepositoryInterface $orderRepository,
        MagentoPaymentInformationManagement $paymentInformationManagement
    ) {
        $this->registry = $registry;
        $this->logging = $logging;
        $this->configProviderMethodFactory  = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentInformationManagement = $paymentInformationManagement;
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * @param  int                   $cartId
     * @param  PaymentInterface      $paymentMethod
     * @param  AddressInterface|null $billingAddress
     * @throws LocalizedException
     * @return string
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ) {

        $this->checkSpecificCountry($paymentMethod, $billingAddress);

        $orderId = $this->paymentInformationManagement->savePaymentInformationAndPlaceOrder($cartId, $paymentMethod, $billingAddress);

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
