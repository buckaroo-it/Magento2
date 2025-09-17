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

namespace Buckaroo\Magento2\Model\Transaction\Status;

use Buckaroo\Magento2\Api\Data\TransactionStatusResponseInterface;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class ProcessResponse
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var TransactionStatusResponseInterface
     */
    protected $response;

    /**
     * @var OrderPaymentInterface|null
     */
    protected $payment;

    /**
     * @var OrderStatusFactory
     */
    protected $statusFactory;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ConfigProviderInterface
     */
    protected $paymentConfig;

    /**
     * @param OrderStatusFactory $statusFactory
     * @param ConfigFactory $configFactory
     * @param Session $checkoutSession
     */
    public function __construct(
        OrderStatusFactory $statusFactory,
        ConfigFactory $configFactory,
        Session $checkoutSession
    ) {
        $this->statusFactory = $statusFactory;
        $this->configFactory = $configFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Process transaction response
     *
     * @param TransactionStatusResponseInterface $response
     * @param Order $order
     * @return array|void
     * @throws NotFoundException|Exception
     */
    public function process(
        TransactionStatusResponseInterface $response,
        Order $order
    ) {
        $this->init($response, $order);
        if ($this->isFailed()) {
            $this->handleFailed();
            return [
                "payment_status" => "failed",
                "status_code"    => $response->getStatusCode()
            ];
        }
        if ($this->isSuccessful()) {
            $this->handleSuccessful();
            return [
                "payment_status" => "success",
                "status_code"    => $response->getStatusCode()
            ];
        }
        if ($this->isProcessing()) {
            return [
                "payment_status" => "processing",
                "status_code"    => $response->getStatusCode()
            ];
        }
    }

    /**
     * Set class properties
     *
     * @param TransactionStatusResponseInterface $response
     * @param Order $order
     * @return void
     * @throws Exception
     * @throws NotFoundException
     */
    protected function init(
        TransactionStatusResponseInterface $response,
        Order $order
    ) {
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

    /**
     * Check if request has failed
     *
     * @return boolean
     */
    protected function isFailed(): bool
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
     * Restore quote on failed
     *
     * @return boolean
     */
    protected function restoreQuote(): bool
    {
        $this->checkoutSession
            ->setLastRealOrderId($this->order->getIncrementId());
        return $this->checkoutSession->restoreQuote();
    }

    /**
     * Is successful transaction
     *
     * @return bool
     */
    protected function isSuccessful(): bool
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
     * Check if request is processing
     *
     * @return boolean
     */
    protected function isProcessing(): bool
    {
        return $this->response->isStatusCode([
            Response::STATUSCODE_WAITING_ON_USER_INPUT,
            Response::STATUSCODE_PENDING_PROCESSING,
            Response::STATUSCODE_WAITING_ON_CONSUMER,
            Response::STATUSCODE_PAYMENT_ON_HOLD,
        ]);
    }
}
