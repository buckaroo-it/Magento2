<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

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

            if (is_null($orderId)) {
                return;
            }

            $order = $this->orderRepositoryInterface->get((int)$orderId);

            if ($order->getState() === Order::STATE_NEW && $order->getId() == $orderId) {
                $this->orderManagementInterface->cancel($order->getEntityId());
                $order->addCommentToStatusHistory(
                    __('Canceled on browser back button')
                )
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->save();
            }

        } catch (\Throwable $th) {
            $this->logger->addError(__METHOD__." ".(string)$th);
        }
    }
}
