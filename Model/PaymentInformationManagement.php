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

use Magento\Checkout\Model\PaymentInformationManagement as MagentoPaymentInformationManagement;
use Buckaroo\Magento2\Api\PaymentInformationManagementInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;

// @codingStandardsIgnoreStart
/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentInformationManagement extends MagentoPaymentInformationManagement implements PaymentInformationManagementInterface
// @codingStandardsIgnoreEnd
{
    protected $registry = null;
    protected $logger = null;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface  $paymentMethodManagement
     * @param \Magento\Quote\Api\CartManagementInterface           $cartManagement
     * @param \Magento\Checkout\Model\PaymentDetailsFactory        $paymentDetailsFactory
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface      $cartTotalsRepository
     * @param \Magento\Framework\Registry                          $registry
     * @param \Psr\Log\LoggerInterface                             $logger
     * @param Factory                                              $configProviderMethodFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface          $orderRepository
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Checkout\Model\PaymentDetailsFactory $paymentDetailsFactory,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository,
        \Magento\Framework\Registry $registry,
        \Psr\Log\LoggerInterface $logger,
        Factory $configProviderMethodFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct(
            $billingAddressManagement,
            $paymentMethodManagement,
            $cartManagement,
            $paymentDetailsFactory,
            $cartTotalsRepository
        );
        $this->registry = $registry;
        $this->logger = $logger;
        $this->configProviderMethodFactory  = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * @param  int                                           $cartId
     * @param  \Magento\Quote\Api\Data\PaymentInterface      $paymentMethod
     * @param  \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return string
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {

        $this->checkSpecificCountry($paymentMethod, $billingAddress);

        $orderId = $this->savePaymentInformationAndPlaceOrder($cartId, $paymentMethod, $billingAddress);

        $this->logger->debug('-[RESULT]----------------------------------------');
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $this->logger->debug(print_r($this->registry->registry('buckaroo_response'), true));
        $this->logger->debug('-------------------------------------------------');

        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            return json_encode($this->registry->registry('buckaroo_response')[0]);
        }
        return json_encode([
            "order_number" => $this->getOrderIncrementId($orderId)
        ]);
    }
    protected function getOrderIncrementId($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getIncrementId();
    }

    /**
     * @param $paymentMethod
     * @param $billingAddress
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkSpecificCountry($paymentMethod, $billingAddress)
    {
        $paymentMethodCode = $this->normalizePaymentMethodCode($paymentMethod->getMethod());

        $configAllowSpecific = $this->configProviderMethodFactory->get($paymentMethodCode)->getAllowSpecific();

        if ($configAllowSpecific == 1) {
            $countryId = ($billingAddress === null) ? null : $billingAddress->getCountryId();
            $configSpecificCountry = $this->configProviderMethodFactory->get($paymentMethodCode)->getSpecificCountry();

            if (!in_array($countryId, $configSpecificCountry)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The requested Payment Method is not available for the given billing country.')
                );
            }
        }
    }

    /**
     * @param string $methodCode
     * @return string
     */
    public function normalizePaymentMethodCode($methodCode = '')
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $methodCode));
    }
}
