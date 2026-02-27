<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class CancelOrder
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepositoryInterface;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagementInterface;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepositoryInterface,
        OrderManagementInterface $orderManagementInterface,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->logger = $logger;
    }

    /**
     * Cancel previous order that comes from a restored quote
     *
     * @param PaymentDataObjectInterface $paymentDO
     */
    public function cancelPreviousPendingOrder(PaymentDataObjectInterface $paymentDO)
    {
        try {
            $payment = $paymentDO->getPayment();
            $orderId = $payment->getAdditionalInformation('buckaroo_cancel_order_id');

            if ($orderId === null) {
                return;
            }

            $order = $this->orderRepositoryInterface->get((int)$orderId);

            if ($order->getState() === Order::STATE_NEW && $order->getId() == $orderId) {
                $shouldSkipVoid = $this->shouldSkipVoidForPendingOrder($order);
                $originalRequestOnVoid = null;

                if ($shouldSkipVoid) {
                    $originalRequestOnVoid = BuckarooAdapter::$requestOnVoid;
                    BuckarooAdapter::$requestOnVoid = false;
                }

                try {
                    $this->orderManagementInterface->cancel($order->getEntityId());
                    $order->addCommentToStatusHistory(
                        __('Canceled on browser back button')
                    )
                        ->setIsCustomerNotified(false)
                        ->setEntityName('invoice')
                        ->save();
                } finally {
                    if ($originalRequestOnVoid !== null) {
                        BuckarooAdapter::$requestOnVoid = $originalRequestOnVoid;
                    }
                }
            }

        } catch (\Throwable $th) {
            $this->logger->addError(__METHOD__." ".(string)$th);
        }
    }

    /**
     * Determine if the void API call should be skipped when canceling a pending order.
     *
     * For Klarna KP orders in redirect flow, the customer may not have completed the
     * authorization, so there is no reservation number and nothing to void at the gateway.
     * Attempting the void would cause a "Payment processing encountered an unexpected error".
     *
     * @param Order $order
     * @return bool
     */
    private function shouldSkipVoidForPendingOrder(Order $order): bool
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }

        $methodCode = $payment->getMethod();

        // Klarna KP: skip void if the reservation number is missing (redirect not completed)
        if ($methodCode === 'buckaroo_magento2_klarnakp'
            && !$order->getBuckarooReservationNumber()
        ) {
            $this->logger->addDebug(sprintf(
                '%s - Skipping void for pending Klarna KP order %s: no reservation number '
                . '(customer did not complete redirect authorization).',
                __METHOD__,
                $order->getIncrementId()
            ));
            return true;
        }

        // Klarna MOR: skip void if the DataRequest key is missing (customer did not complete redirect)
        if ($methodCode === 'buckaroo_magento2_klarna'
            && empty($order->getBuckarooDatarequestKey())
            && empty($payment->getAdditionalInformation('buckaroo_datarequest_key'))
        ) {
            $this->logger->addDebug(sprintf(
                '%s - Skipping void for pending Klarna MOR order %s: no DataRequest key '
                . '(customer did not complete redirect authorization).',
                __METHOD__,
                $order->getIncrementId()
            ));
            return true;
        }

        // Afterpay: skip void if the authorization was marked as failed
        if (in_array($methodCode, ['buckaroo_magento2_afterpay', 'buckaroo_magento2_afterpay2'])
            && $payment->getAdditionalInformation('buckaroo_failed_authorize')
        ) {
            $this->logger->addDebug(sprintf(
                '%s - Skipping void for pending order %s: authorization was marked as failed.',
                __METHOD__,
                $order->getIncrementId()
            ));
            return true;
        }

        return false;
    }
}
