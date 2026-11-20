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

namespace Buckaroo\Magento2\Model\PaypalExpress;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal as PaypalConfig;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Api\PaypalExpressOrderCreateInterface;
use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;

class OrderCreate implements PaypalExpressOrderCreateInterface
{
    /**
     * @var \Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteId
     */
    protected $maskedQuoteIdToQuoteId;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $quoteManagement;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateFactory
     */
    protected $orderUpdateFactory;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    /**
     * @var PaypalConfig
     */
    protected $paypalConfig;

    /**
     * Placeholder values set by ensureRequiredAddressFields(), per address type
     *
     * @var array
     */
    private $pendingFields = [];

    public function __construct(
        OrderCreateResponseInterfaceFactory $responseFactory,
        CartManagementInterface $quoteManagement,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderUpdateFactory $orderUpdateFactory,
        Log $logger,
        ?PaypalConfig $paypalConfig = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteManagement = $quoteManagement;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->orderUpdateFactory = $orderUpdateFactory;
        $this->logger = $logger;
        $this->paypalConfig = $paypalConfig ?? ObjectManager::getInstance()->get(PaypalConfig::class);
    }

    /** @inheritDoc */
    public function execute(
        string $paypal_order_id,
        ?string $cart_id = null
    ) {
        if (!$this->isExpressCheckoutAvailable()) {
            throw new PaypalExpressException(__("PayPal Express checkout is not available"));
        }

        try {
            $orderId = $this->createOrder($paypal_order_id, $cart_id);
        } catch (NoSuchEntityException $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw new PaypalExpressException(__("Failed to create order"), 1, $th);
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw $th;
        }

        return $this->responseFactory->create(["orderId" => $orderId]);
    }

    /**
     * Check that PayPal and at least one of its express buttons are enabled
     *
     * @return bool
     */
    protected function isExpressCheckoutAvailable(): bool
    {
        $buttons = $this->paypalConfig->getExpressButtons();

        return $this->paypalConfig->isPayPalEnabled() && is_string($buttons) && $buttons !== '';
    }

    /**
     * Place order based on quote and paypal order id
     *
     * @param string $paypal_order_id
     * @param string $cart_id
     *
     * @return string
     */
    protected function createOrder(
        string $paypal_order_id,
        string $cart_id
    ) {

        $quote = $this->getQuote($cart_id);
        $quote->getPayment()->setAdditionalInformation('express_order_id', $paypal_order_id);
        $quote->reserveOrderId();
        $this->checkQuoteBelongsToLoggedUser($quote);

        $this->ensureRequiredAddressFields($quote);
        $this->ignoreAddressValidation($quote);
        $this->quoteRepository->save($quote);

        try {
            $orderId = $this->quoteManagement->placeOrder($quote->getId());
        } catch (\Throwable $e) {
            $this->clearPendingAddressFields($quote);
            throw $e;
        }

        $order = $this->orderRepository->get($orderId);
        $this->updateOrder($order);
        $this->setLastOrderToSession($order);
        return $order->getIncrementId();
    }

    /**
     * Ensure required address fields are set with minimal values to pass Magento validation
     *
     * These will be replaced with the PayPal data in updateOrder()
     *
     * @param Quote $quote
     */
    private function ensureRequiredAddressFields(Quote $quote)
    {
        $this->pendingFields = [
            'shipping' => $this->setPendingFieldsOnAddress($quote->getShippingAddress()),
            'billing'  => $this->setPendingFieldsOnAddress($quote->getBillingAddress()),
            'quote'    => [],
        ];
        $this->copyAddressFieldsToBilling($quote->getShippingAddress(), $quote->getBillingAddress());

        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail('pending@paypal.customer');
            $this->pendingFields['quote'][] = 'customer_email';
        }
    }

    /**
     * Set pending values for required address fields
     *
     * @param Address $address
     * @return string[] The fields that received a pending value
     */
    private function setPendingFieldsOnAddress($address): array
    {
        $filled = [];
        if ($this->isBlank($address->getFirstname())) {
            $address->setFirstname('PayPal');
            $filled[] = 'firstname';
        }
        if ($this->isBlank($address->getLastname())) {
            $address->setLastname('Customer');
            $filled[] = 'lastname';
        }
        if ($this->isBlank(implode('', (array)$address->getStreet()))) {
            $address->setStreet(['Pending']);
            $filled[] = 'street';
        }
        if ($this->isBlank($address->getTelephone())) {
            $address->setTelephone('000-000-0000');
            $filled[] = 'telephone';
        }
        if ($this->isBlank($address->getEmail())) {
            $address->setEmail('pending@paypal.customer');
            $filled[] = 'email';
        }

        return $filled;
    }

    /**
     * Whether Magento's address validation treats the value as empty.
     *
     * The validation trims before it checks, so a value of only spaces is empty too.
     *
     * @param mixed $value
     * @return bool
     */
    private function isBlank($value): bool
    {
        return trim((string)$value) === '';
    }

    /**
     * Copy address fields from shipping to billing address
     *
     * @param Address $shippingAddress
     * @param Address $billingAddress
     * @return void
     */
    private function copyAddressFieldsToBilling($shippingAddress, $billingAddress)
    {
        $billingCountry = $billingAddress->getCountryId();
        if ($billingCountry && $billingCountry !== $shippingAddress->getCountryId()) {
            return;
        }

        $fieldsToCopy = ['City', 'Postcode', 'CountryId', 'RegionId', 'Region'];

        foreach ($fieldsToCopy as $field) {
            $getter = 'get' . $field;
            $setter = 'set' . $field;

            if (!$billingAddress->$getter() && $shippingAddress->$getter()) {
                $billingAddress->$setter($shippingAddress->$getter());
            }
        }
    }

    /**
     * Remove placeholder address fields from the quote and persist the cleanup.
     *
     * Called when placeOrder() fails so that the values set by
     * ensureRequiredAddressFields() do not end up in the standard checkout form.
     * Only the fields that ensureRequiredAddressFields() filled are cleared.
     *
     * @param Quote $quote
     * @return void
     */
    private function clearPendingAddressFields(Quote $quote): void
    {
        $addresses = [
            'shipping' => $quote->getShippingAddress(),
            'billing'  => $quote->getBillingAddress(),
        ];

        foreach ($addresses as $type => $address) {
            foreach ($this->pendingFields[$type] ?? [] as $field) {
                if ($field === 'street') {
                    $address->setStreet([]);
                } else {
                    $address->setData($field, '');
                }
            }
        }

        if (in_array('customer_email', $this->pendingFields['quote'] ?? [], true)) {
            $quote->setCustomerEmail('');
        }
        $this->pendingFields = [];

        try {
            $this->quoteRepository->save($quote);
        } catch (\Throwable $saveException) {
            $this->logger->addDebug(
                __METHOD__ . ' Failed to clear pending address fields: ' . $saveException->getMessage()
            );
        }
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @param Quote $quote
     */
    private function ignoreAddressValidation(Quote $quote)
    {
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        $quote->getShippingAddress()->setShouldIgnoreValidation(true);
    }

    protected function updateOrder(OrderInterface $order)
    {
        $orderUpdateService = $this->orderUpdateFactory->create();
        $orderUpdateService->updateAddress($order->getShippingAddress());
        $orderUpdateService->updateAddress($order->getBillingAddress());
        $orderUpdateService->updateEmail($order);
        $this->orderRepository->save($order);
    }
    /**
     * Check if quote belongs to the current logged in user
     *
     * @param CartInterface $quote
     *
     * @throws \Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException
     */
    protected function checkQuoteBelongsToLoggedUser(CartInterface $quote)
    {
        if ($this->customerSession->getCustomerId() !== $quote->getCustomer()->getId()) {
            throw new PaypalExpressException(__('Cannot create order for this user'));
        }
    }

    /**
     * Update session with last order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    protected function setLastOrderToSession(OrderInterface $order)
    {
        $this->checkoutSession
        ->setLastQuoteId($order->getQuoteId())
        ->setLastSuccessQuoteId($order->getQuoteId())
        ->setLastOrderId($order->getEntityId())
        ->setLastRealOrderId($order->getIncrementId())
        ->setLastOrderStatus($order->getStatus());
    }
    /**
     * Get quote from masked quote/cart id
     *
     * @param string $cart_id
     *
     * @return Quote
     */
    protected function getQuote($cart_id)
    {
        return $this->quoteRepository->get(
            $this->maskedQuoteIdToQuoteId->execute($cart_id)
        );
    }
}
