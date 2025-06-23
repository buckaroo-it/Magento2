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

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Api\PaymentInformationManagementInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Checkout\Model\PaymentDetailsFactory;
use Magento\Checkout\Model\PaymentInformationManagement as MagentoPaymentInformationManagement;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PaymentInformationManagement
 *
 * This class extends the MagentoPaymentInformationManagement class and implements
 * the PaymentInformationManagementInterface. It is responsible for managing payment
 * information and placing orders for a specified cart in the Buckaroo payment system.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentInformationManagement extends MagentoPaymentInformationManagement implements
    PaymentInformationManagementInterface
{
    /**
     * @var Factory
     */
    public Factory $configProviderMethodFactory;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var BuckarooResponseDataInterface
     */
    private BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @param BillingAddressManagementInterface $billingAddressManagement
     * @param PaymentMethodManagementInterface $paymentMethodManagement
     * @param CartManagementInterface $cartManagement
     * @param PaymentDetailsFactory $paymentDetailsFactory
     * @param CartTotalRepositoryInterface $cartTotalsRepository
     * @param BuckarooResponseDataInterface $buckarooResponseData
     * @param LoggerInterface $logger
     * @param Factory $configProviderMethodFactory
     * @param OrderRepositoryInterface $orderRepository
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        BillingAddressManagementInterface $billingAddressManagement,
        PaymentMethodManagementInterface $paymentMethodManagement,
        CartManagementInterface $cartManagement,
        PaymentDetailsFactory $paymentDetailsFactory,
        CartTotalRepositoryInterface $cartTotalsRepository,
        BuckarooResponseDataInterface $buckarooResponseData,
        LoggerInterface $logger,
        Factory $configProviderMethodFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct(
            $billingAddressManagement,
            $paymentMethodManagement,
            $cartManagement,
            $paymentDetailsFactory,
            $cartTotalsRepository
        );
        $this->buckarooResponseData = $buckarooResponseData;
        $this->logger = $logger;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return string
     * @throws CouldNotSaveException|LocalizedException
     */
    public function buckarooSavePaymentInformationAndPlaceOrder(
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): string {
        $this->checkSpecificCountry($paymentMethod, $billingAddress);

        try {
            $orderId = $this->savePaymentInformationAndPlaceOrder($cartId, $paymentMethod, $billingAddress);

            $this->logger->debug('iDEAL Fast Checkout - Order placed successfully', [
                'order_id' => $orderId
            ]);
        } catch (\Exception $e) {
            $this->logger->debug('iDEAL Fast Checkout - Order placement failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        if ($buckarooResponse = $this->buckarooResponseData->getResponse()) {
            $buckarooResponse = $buckarooResponse->toArray();
            $this->logger->debug(sprintf(
                '[PLACE_ORDER] | [Webapi] | [%s:%s] - Logged In Users | buckarooResponse: %s',
                __METHOD__,
                __LINE__,
                print_r($buckarooResponse, true)
            ));

            if ($buckarooResponse) {
                return \json_encode($buckarooResponse);
            }
        }

        return json_encode([
            "limitReachedMessage" => $this->getLimitReachedMessage($orderId),
            "order_number"        => $this->getOrderIncrementId($orderId)
        ]);
    }

    /**
     * Check if the payment method is available for the given billing address country.
     *
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @throws LocalizedException
     */
    public function checkSpecificCountry(PaymentInterface $paymentMethod, ?AddressInterface $billingAddress)
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
     * Normalize payment method code by removing the prefix.
     *
     * @param string $methodCode
     * @return string
     */
    public function normalizePaymentMethodCode(string $methodCode = ''): string
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $methodCode));
    }

    /**
     * Get order increment id by order id.
     *
     * @param int|string $orderId
     * @return string|null
     */
    protected function getOrderIncrementId($orderId): ?string
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getIncrementId();
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
            return $order->getPayment()->getAdditionalInformation(BuckarooAdapter::PAYMENT_ATTEMPTS_REACHED_MESSAGE);
        }
        return null;
    }
}
