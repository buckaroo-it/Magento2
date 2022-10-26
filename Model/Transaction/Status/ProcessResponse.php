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

namespace Buckaroo\Magento2\Model\Transaction\Status;

use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Magento\Framework\Exception\NotFoundException;
use Buckaroo\Magento2\Api\TransactionResponseInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigFactory;

class ProcessResponse
{

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Buckaroo\Magento2\Api\TransactionResponseInterface
     */
    protected $response;

    /**
     * @var \Magento\Sales\Api\Data\OrderPaymentInterface|null
     */
    protected $payment;

    /**
     * @var \Buckaroo\Magento2\Model\OrderStatusFactory
     */
    protected $statusFactory;

     /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory
     */
    protected $configFactory;

     /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    protected $paymentConfig;

    public function __construct(
        OrderStatusFactory $statusFactory,
        ConfigFactory $configFactory,
        Session $checkoutSession
    ) {
        $this->statusFactory = $statusFactory;
        $this->configFactory = $configFactory;
        $this->checkoutSession = $checkoutSession;
    }
    public function process(
        TransactionResponseInterface $response,
        Order $order
    )
    {
        $this->init($response, $order);
        if($this->isFailed()) {
            $this->handleFailed();
            return [
                "payment_status" => "failed",
                "status_code" => $response->getStatusCode()
            ];
        }
        if($this->isSuccessful()) {
            $this->handleSuccessful();
            return [
                "payment_status" => "success",
                "status_code" => $response->getStatusCode()
            ];
        }
        if ($this->isProcessing()) {
            return [
                "payment_status" => "processing",
                "status_code" => $response->getStatusCode()
            ];
        }
    }

    /**
     * Check if request is processing
     *
     * @return boolean
     */
    protected function isProcessing()
    {
        return $this->response->isStatusCode([
            Response::STATUSCODE_WAITING_ON_USER_INPUT, 
            Response::STATUSCODE_PENDING_PROCESSING,    
            Response::STATUSCODE_WAITING_ON_CONSUMER,   
            Response::STATUSCODE_PAYMENT_ON_HOLD,      
        ]);
    }

    /**
     * Check if request has failed
     *
     * @return boolean
     */
    protected function isFailed()
    {
        return $this->response->isStatusCode([
            Response::STATUSCODE_REJECTED,
            Response::STATUSCODE_TECHNICAL_ERROR,
            Response::STATUSCODE_VALIDATION_FAILURE,
            Response::STATUSCODE_CANCELLED_BY_MERCHANT,
            Response::STATUSCODE_CANCELLED_BY_USER,
            Response::STATUSCODE_FAILED
        ]);
    }

    protected function isSuccessful()
    {
        return $this->response->isStatusCode(Response::STATUSCODE_SUCCESS);
    }

    /**
     * Handle state when successful
     *
     * @return void
     */
    protected function handleSuccessful()
    {
        $this->updateCheckoutSession();
    }
    /**
     * Handle state when failed
     *
     * @return void
     */
    protected function handleFailed()
    {
        $this->cancelOrder();
        $this->restoreQuote();
    }

    /**
     * Restore quote on failed
     *
     * @return boolean
     */
    protected function restoreQuote()
    {
        $this->checkoutSession
            ->setLastRealOrderId($this->order->getIncrementId());
        return $this->checkoutSession->restoreQuote();
    }

    /**
     * Update checkout session with the last order data
     *
     * @return void
     */
    protected function updateCheckoutSession()
    {
        $this->checkoutSession
            ->setLastQuoteId($this->order->getQuoteId())
            ->setLastOrderId($this->order->getId())
            ->setLastRealOrderId($this->order->getIncrementId())
            ->setLastOrderStatus($this->order->getStatus())
            ->setLastSuccessQuoteId($this->order->getQuoteId());
    }
    /**
     * Cancel order when failed
     *
     * @return void
     */
    protected function cancelOrder()
    {
        if (!$this->order->isCanceled()) {
            $this->order->cancel();
        }
    }
    /**
     * Set class properties 
     *
     * @return void
     */
    protected function init(
        TransactionResponseInterface $response,
        Order $order
    )
    {
        $this->response = $response;
        $this->order = $order;
        $this->payment = $this->order->getPayment();

        if ($this->payment === null) {
            throw new NotFoundException(__('Cannot process order, no payment found'));
        }

        $this->paymentConfig = $this->configFactory->get(
            $this->payment->getMethod()
        );
    }
}
