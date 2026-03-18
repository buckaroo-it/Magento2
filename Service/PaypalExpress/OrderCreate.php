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

namespace Buckaroo\Magento2\Service\PaypalExpress;

use Buckaroo\Magento2\Service\PaypalExpress\PaypalExpressException;
use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
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
use Buckaroo\Magento2\Service\PaypalExpress\OrderUpdateFactory;
use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
     * @var \Buckaroo\Magento2\Service\PaypalExpress\OrderUpdateFactory
     */
    protected $orderUpdateFactory;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param OrderCreateResponseInterfaceFactory $responseFactory
     * @param CartManagementInterface $quoteManagement
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderUpdateFactory $orderUpdateFactory
     * @param Log $logger
     */
    public function __construct(
        OrderCreateResponseInterfaceFactory $responseFactory,
        CartManagementInterface $quoteManagement,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderUpdateFactory $orderUpdateFactory,
        Log $logger
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
    }

    /**
     * @inheritDoc
     */
    public function execute(
        string $paypal_order_id,
        ?string $cart_id = null
    ) {
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

        // Set minimal required fields to pass Magento validation
        // These will be immediately replaced with real PayPal data after order placement
        $this->ensureRequiredAddressFields($quote);

        $this->ignoreAddressValidation($quote);
        $this->quoteRepository->save($quote);  // Save quote after setting ignore validation flags

        $this->checkQuoteBelongsToLoggedUser($quote);

        $this->logger->addDebug('[PayPal Express OrderCreate] Placing order with pending address values...');
        $orderId = $this->quoteManagement->placeOrder($quote->getId());
        $this->logger->addDebug('[PayPal Express OrderCreate] Order placed successfully with ID: ' . $orderId);

        $order = $this->orderRepository->get($orderId);

        $this->logger->addDebug('[PayPal Express OrderCreate] Updating order with real PayPal data...');
        $this->updateOrder($order);
        $this->logger->addDebug('[PayPal Express OrderCreate] Order updated with real PayPal data');

        $this->setLastOrderToSession($order);
        return $order->getIncrementId();
    }

    /**
     * Ensure required address fields are set with minimal values to pass Magento validation
     *
     * These will be immediately replaced with real PayPal data in updateOrder()
     *
     * @param Quote $quote
     */
    private function ensureRequiredAddressFields(Quote $quote)
    {
        $this->setPendingFieldsOnAddress($quote->getShippingAddress());
        $this->setPendingFieldsOnAddress($quote->getBillingAddress());
        $this->copyAddressFieldsToBilling($quote->getShippingAddress(), $quote->getBillingAddress());

        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail('pending@paypal.customer');
        }
    }

    /**
     * Set pending values for required address fields
     *
     * @param \Magento\Quote\Model\Quote\Address $address
     * @return void
     */
    private function setPendingFieldsOnAddress($address)
    {
        if (!$address->getFirstname()) {
            $address->setFirstname('PayPal');
        }
        if (!$address->getLastname()) {
            $address->setLastname('Customer');
        }
        if (!$address->getStreet() || empty($address->getStreet()[0])) {
            $address->setStreet(['Pending']);
        }
        if (!$address->getTelephone()) {
            $address->setTelephone('000-000-0000');
        }
        if (!$address->getEmail()) {
            $address->setEmail('pending@paypal.customer');
        }
    }

    /**
     * Copy address fields from shipping to billing address
     *
     * @param \Magento\Quote\Model\Quote\Address $shippingAddress
     * @param \Magento\Quote\Model\Quote\Address $billingAddress
     * @return void
     */
    private function copyAddressFieldsToBilling($shippingAddress, $billingAddress)
    {
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
     * Make sure addresses will be saved without validation errors
     *
     * @param Quote $quote
     */
    private function ignoreAddressValidation(Quote $quote)
    {
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        $quote->getShippingAddress()->setShouldIgnoreValidation(true);
    }

    /**
     * Update order with real PayPal data
     *
     * @param OrderInterface $order
     * @return void
     */
    protected function updateOrder(OrderInterface $order)
    {
        $orderUpdateService = $this->orderUpdateFactory->create();
        $orderUpdateService->updateAddress($order->getShippingAddress());
        $orderUpdateService->updateAddress($order->getBillingAddress());
        $orderUpdateService->updateEmail($order);
        $orderUpdateService->updateCustomerName($order);
        $this->orderRepository->save($order);
    }

    /**
     * Check if quote belongs to the current logged in user
     *
     * @param CartInterface $quote
     *
     * @throws \Buckaroo\Magento2\Service\PaypalExpress\PaypalExpressException
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
