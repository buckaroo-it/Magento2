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

use Magento\Checkout\Model\GuestPaymentInformationManagement as MagentoGuestPaymentInformationManagement;
use Buckaroo\Magento2\Api\GuestPaymentInformationManagementInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Framework\App\ProductMetadataInterface;

// @codingStandardsIgnoreStart
class GuestPaymentInformationManagement extends MagentoGuestPaymentInformationManagement implements GuestPaymentInformationManagementInterface
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
     * @param \Magento\Quote\Api\GuestBillingAddressManagementInterface   $billingAddressManagement
     * @param \Magento\Quote\Api\GuestPaymentMethodManagementInterface    $paymentMethodManagement
     * @param \Magento\Quote\Api\GuestCartManagementInterface             $cartManagement
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManagement
     * @param \Magento\Quote\Model\QuoteIdMaskFactory                     $quoteIdMaskFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface                  $cartRepository
     * @param \Magento\Framework\Registry                                 $registry
     * @param \Psr\Log\LoggerInterface                                    $logger
     * @param Factory                                                     $configProviderMethodFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface                 $orderRepository
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Quote\Api\GuestBillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\GuestPaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\GuestCartManagementInterface $cartManagement,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformationManagement,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Registry $registry,
        \Psr\Log\LoggerInterface $logger,
        Factory $configProviderMethodFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        ProductMetadataInterface $productMetadata
    ) {
        $magentoVersion = $productMetadata->getVersion();
        $lastParam = null;

        if (version_compare($magentoVersion, '2.4.6', '>=')) {
            $lastParam = $logger;
        }

        parent::__construct(
            $billingAddressManagement,
            $paymentMethodManagement,
            $cartManagement,
            $paymentInformationManagement,
            $quoteIdMaskFactory,
            $cartRepository,
            $lastParam
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
     * @param  string                                        $email
     * @param  \Magento\Quote\Api\Data\PaymentInterface      $paymentMethod
     * @param  \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return string
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {

        $this->checkSpecificCountry($paymentMethod, $billingAddress);

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        $quote->reserveOrderId();

        $orderId = $this->savePaymentInformationAndPlaceOrder($cartId, $email, $paymentMethod, $billingAddress);

        $this->logger->debug('-[RESULT]----------------------------------------');
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $this->logger->debug(print_r($this->registry->registry('buckaroo_response'), true));
        $this->logger->debug('-------------------------------------------------');

        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            return json_encode([
                "buckaroo_response" => $this->registry->registry('buckaroo_response')[0],
                "order_id" => $orderId
            ]);
        }
        return json_encode([
            "limitReachedMessage" => $this->getLimitReachedMessage($orderId),
            "order_number" => $this->getOrderIncrementId($orderId)
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
        if($order->getEntityId() !== null && $order->getPayment() !== null) {
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
     * @param $paymentMethod
     * @param $billingAddress
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkSpecificCountry($paymentMethod, $billingAddress)
    {
        $paymentMethodCode = $this->normalizePaymentMethodCode($paymentMethod->getMethod());

        $configAllowSpecific = $this->configProviderMethodFactory->get($paymentMethodCode)->getAllowSpecific();

        if ($configAllowSpecific == 1) {
            $countryId = ($billingAddress === null ) ? null : $billingAddress->getCountryId();
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
