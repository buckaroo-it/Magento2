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

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterfaceFactory;
use Buckaroo\Magento2\Api\PaypalExpressOrderCreateInterface;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\PaypalExpress\OrderUpdateShippingFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderCreate implements PaypalExpressOrderCreateInterface
{
    /**
     * @var OrderCreateResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var MaskedQuoteIdToQuoteId
     */
    protected $maskedQuoteIdToQuoteId;

    /**
     * @var CartManagementInterface
     */
    protected $quoteManagement;
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderUpdateShippingFactory
     */
    protected $orderUpdateShippingFactory;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @param OrderCreateResponseInterfaceFactory $responseFactory
     * @param CartManagementInterface $quoteManagement
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderUpdateShippingFactory $orderUpdateShippingFactory
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

    /**
     * @inheritdoc
     */
    public function execute(
        string $paypalOrderId,
        string $cartId = null
    ) {
        try {
            $orderId = $this->createOrder($paypalOrderId, $cartId);
        } catch (NoSuchEntityException $th) {
            $this->logger->addDebug(__METHOD__ . $th->getMessage());
            throw new PaypalExpressException(__("Failed to create order"), 1, $th);
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . $th->getMessage());
            throw $th;
        }

        return $this->responseFactory->create(["orderId" => $orderId]);
    }

    /**
     * Place order based on quote and paypal order id
     *
     * @param string $paypalOrderId
     * @param string $cartId
     *
     * @return string
     * @throws LocalizedException
     * @throws PaypalExpressException
     */
    protected function createOrder(
        string $paypalOrderId,
        string $cartId
    ) {

        $quote = $this->getQuote($cartId);
        $quote->getPayment()->setAdditionalInformation('express_order_id', $paypalOrderId);

        $this->checkQuoteBelongsToLoggedUser($quote);
        $orderId = $this->quoteManagement->placeOrder($quote->getId());

        $order = $this->orderRepository->get($orderId);
        $this->updateOrderShipping($order);
        $this->setLastOrderToSession($order);
        return $order->getIncrementId();
    }

    /**
     * Get quote from masked quote/cart id
     *
     * @param string $cartId
     * @return Quote
     * @throws NoSuchEntityException
     */
    protected function getQuote($cartId)
    {
        return $this->quoteRepository->get(
            $this->maskedQuoteIdToQuoteId->execute($cartId)
        );
    }

    /**
     * Check if quote belongs to the current logged in user
     *
     * @param CartInterface $quote
     *
     * @return void
     * @throws PaypalExpressException
     */
    protected function checkQuoteBelongsToLoggedUser(CartInterface $quote)
    {
        if ($this->customerSession->getCustomerId() !== $quote->getCustomer()->getId()) {
            throw new PaypalExpressException(__('Cannot create order for this user'));
        }
    }

    /**
     * Update shipping address
     *
     * @param OrderInterface $order
     * @return void
     */
    protected function updateOrderShipping(OrderInterface $order)
    {
        $orderUpdateShipping = $this->orderUpdateShippingFactory->create([
            "shippingAddress" => $order->getShippingAddress()
        ]);
        $orderUpdateShipping->update();
        $this->orderRepository->save($order);
    }

    /**
     * Update session with last order
     *
     * @param OrderInterface $order
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
}
