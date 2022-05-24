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
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateShippingFactory;
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
     * @var \Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateShippingFactory
     */
    protected $orderUpdateShippingFactory;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    public function __construct(
        OrderCreateResponseInterfaceFactory $responseFactory,
        CartManagementInterface $quoteManagement,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderUpdateShippingFactory $orderUpdateShippingFactory,
        Log $logger
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteManagement = $quoteManagement;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->orderUpdateShippingFactory = $orderUpdateShippingFactory;
        $this->logger = $logger;
    }
    
    /** @inheritDoc */
    public function execute(
        string $paypal_order_id,
        string $cart_id = null
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

        $this->checkQuoteBelongsToLoggedUser($quote);
        $orderId = $this->quoteManagement->placeOrder($quote->getId());

        $order = $this->orderRepository->get($orderId);
        $this->updateOrderShipping($order);
        $this->setLastOrderToSession($order);
        return $order->getIncrementId();
    }
    protected function updateOrderShipping(OrderInterface $order)
    {
        $orderUpdateShipping = $this->orderUpdateShippingFactory->create(["shippingAddress" => $order->getShippingAddress()]);
        $orderUpdateShipping->update();
        $this->orderRepository->save($order);
    }
    /**
     * Check if quote belongs to the current logged in user
     *
     * @param CartInterface $quote
     *
     * @return void
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
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote($cart_id)
    {
        return $this->quoteRepository->get(
            $this->maskedQuoteIdToQuoteId->execute($cart_id)
        );
    }
}
