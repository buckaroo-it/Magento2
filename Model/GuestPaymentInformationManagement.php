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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\GuestPaymentInformationManagementInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Model\GuestPaymentInformationManagement as MagentoGuestPaymentInformationManagement;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\GuestBillingAddressManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestPaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Class GuestPaymentInformationManagement
 *
 * Extends the MagentoGuestPaymentInformationManagement class to customize the guest payment and
 * order placement process for Buckaroo payments.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GuestPaymentInformationManagement extends MagentoGuestPaymentInformationManagement
    implements GuestPaymentInformationManagementInterface
{
    /**
     * @var Factory
     */
    public Factory $configProviderMethodFactory;
    /**
     * @var Registry|null
     */
    protected ?Registry $registry = null;
    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @param GuestBillingAddressManagementInterface $billingAddressManagement
     * @param GuestPaymentMethodManagementInterface $paymentMethodManagement
     * @param GuestCartManagementInterface $cartManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param Factory $configProviderMethodFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ProductMetadataInterface $productMetadata
     * @codeCoverageIgnore
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        GuestBillingAddressManagementInterface $billingAddressManagement,
        GuestPaymentMethodManagementInterface $paymentMethodManagement,
        GuestCartManagementInterface $cartManagement,
        PaymentInformationManagementInterface $paymentInformationManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        Registry $registry,
        LoggerInterface $logger,
        Factory $configProviderMethodFactory,
        OrderRepositoryInterface $orderRepository,
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
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * @param int $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return string|false
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): string {

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
            return \json_encode($this->registry->registry('buckaroo_response')[0]);
        }
        return json_encode([
//            "limitReachedMessage" => $this->getLimitReachedMessage($orderId),
            "order_number" => $this->getOrderIncrementId($orderId)
        ]);
    }

    /**
     * Get limit reach message from payment object
     *
     * @param int $orderId
     * @return string|null
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getLimitReachedMessage($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        if ($order->getEntityId() !== null && $order->getPayment() !== null) {
            return $order->getPayment()->getAdditionalInformation(BuckarooAdapter::PAYMENT_ATTEMPTS_REACHED_MESSAGE);
        }
        return null;
    }

    /**
     * Check if the payment method is allowed for the given billing address country.
     *
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @throws LocalizedException
     */
    public function checkSpecificCountry(PaymentInterface $paymentMethod, AddressInterface $billingAddress)
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
     * Normalize the payment method code.
     *
     * @param string $methodCode
     * @return string
     */
    public function normalizePaymentMethodCode(string $methodCode = ''): string
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $methodCode));
    }

    /**
     * Retrieve the order increment ID by order ID.
     *
     * @param int $orderId
     * @return string
     */
    protected function getOrderIncrementId(int $orderId): string
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getIncrementId();
    }
}
