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

namespace Buckaroo\Magento2\Model\Ideal;

use Buckaroo\Magento2\Model\Method\AbstractMethod;
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
use Buckaroo\Magento2\Api\IdealOrderCreateInterface;
use Buckaroo\Magento2\Model\Ideal\IdealException;
use Buckaroo\Magento2\Model\Ideal\OrderUpdateFactory;
use Buckaroo\Magento2\Api\Data\Ideal\OrderCreateResponseInterfaceFactory;

class OrderCreate implements IdealOrderCreateInterface
{
    /**
     * @var \Buckaroo\Magento2\Api\Data\Ideal\OrderCreateResponseInterfaceFactory
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
     * @var \Buckaroo\Magento2\Model\Ideal\OrderUpdateFactory
     */
    protected $orderUpdateFactory;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    protected $registry = null;


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
        \Magento\Framework\Registry $registry
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
        $this->registry = $registry;
    }

    /** @inheritDoc */
    public function execute(
        string $cart_id = null
    ) {
        try {
            $response = $this->createOrder($cart_id);
        } catch (NoSuchEntityException $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw new IdealException(__("Failed to create order"), 1, $th);
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__.$th->getMessage());
            throw $th;
        }

        return $this->responseFactory->create($response);
    }

    /**
     * Place order based on quote and paypal order id
     *
     * @param string $cart_id
     *
     * @return string
     */
    protected function createOrder(
        string $cart_id
    ) {

        $quote = $this->getQuote($cart_id);
        $quote->reserveOrderId();
        $this->ignoreAddressValidation($quote);
        $this->checkQuoteBelongsToLoggedUser($quote);
        $orderId = $this->quoteManagement->placeOrder($quote->getId());

        $order = $this->orderRepository->get($orderId);
        $this->updateOrder($order);
        $this->setLastOrderToSession($order);

        $orderData = [
            "order_number" => $order->getIncrementId(),
            "limitReachedMessage" => $this->getLimitReachedMessage($orderId),
        ];

        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            $buckarooResponse = $this->registry->registry('buckaroo_response')[0];
            $buckarooResponseArray = json_decode(json_encode($buckarooResponse), true);
            $orderData = array_merge($orderData, $buckarooResponseArray);
        }

        return $orderData;
    }

    private function getLimitReachedMessage($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        if($order->getEntityId() !== null && $order->getPayment() !== null) {
            return $order->getPayment()->getAdditionalInformation(AbstractMethod::PAYMENT_ATTEMPTS_REACHED_MESSAGE);
        }
        return null;
    }

     /**
     * Make sure addresses will be saved without validation errors
     *
     * @return void
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
     * @return void
     * @throws \Buckaroo\Magento2\Model\Ideal\IdealException
     */
    protected function checkQuoteBelongsToLoggedUser(CartInterface $quote)
    {
        if ($this->customerSession->getCustomerId() !== $quote->getCustomer()->getId()) {
            throw new IdealException('Cannot create order for this user');
        }
    }

    /**
     * Update session with last order
     *
     * @param  \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return void
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
