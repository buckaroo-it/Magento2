<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter as SdkAdapter;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Buckaroo\Magento2\Service\Order\OrderCommentHistoryService;
use Magento\Framework\App\ObjectManager;
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
     * @var OrderCommentHistoryService
     */
    private $orderCommentHistoryService;

    /**
     * Buckaroo status that means the previous order is paid. Pending statuses (790-793) also mean
     * "waiting for the shopper" for redirect methods, so they do not keep the order; if it is paid
     * after all, its push reactivates it.
     */
    private const PAID_OR_PROCESSING_STATUSES = [190];

    /**
     * @var CancelGuard|null
     */
    private ?CancelGuard $cancelGuard = null;

    /**
     * @var SdkAdapter|null
     */
    private ?SdkAdapter $sdkAdapter = null;

    /**
     * @param OrderRepositoryInterface   $orderRepositoryInterface
     * @param OrderManagementInterface   $orderManagementInterface
     * @param BuckarooLoggerInterface    $logger
     * @param OrderCommentHistoryService $orderCommentHistoryService
     */
    public function __construct(
        OrderRepositoryInterface $orderRepositoryInterface,
        OrderManagementInterface $orderManagementInterface,
        BuckarooLoggerInterface $logger,
        OrderCommentHistoryService $orderCommentHistoryService
    ) {
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->logger = $logger;
        $this->orderCommentHistoryService = $orderCommentHistoryService;
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

            if ($order->getState() !== Order::STATE_NEW || $order->getId() != $orderId) {
                return;
            }

            if ($this->isPaidOrProcessingAtBuckaroo($order)) {
                $this->logger->addDebug(sprintf(
                    '[CANCEL_ORDER] | [Service] | [%s:%s] - Previous order %s is paid or being processed at'
                    . ' Buckaroo, leaving it for its push',
                    __METHOD__,
                    __LINE__,
                    $order->getIncrementId()
                ));
                return;
            }

            $shouldSkipVoid = $this->shouldSkipVoidForPendingOrder($order);
            $originalRequestOnVoid = null;

            if ($shouldSkipVoid) {
                $originalRequestOnVoid = BuckarooAdapter::$requestOnVoid;
                BuckarooAdapter::$requestOnVoid = false;
            }

            try {
                // Under the previous order's own lock, re-read: its push may be running right now
                $cancelled = $this->getCancelGuard()->cancel((int)$orderId, 'restored quote (browser back)');
                if ($cancelled) {
                    $this->orderCommentHistoryService->add($order, __('Canceled on browser back button'));
                }
            } finally {
                if ($originalRequestOnVoid !== null) {
                    BuckarooAdapter::$requestOnVoid = $originalRequestOnVoid;
                }
            }

        } catch (\Throwable $th) {
            $this->logger->addError(__METHOD__." ".(string)$th);
        }
    }

    /**
     * Whether Buckaroo already reports the previous order's transaction as paid
     *
     * The shopper may have paid in the bank app and come back before the push arrived.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isPaidOrProcessingAtBuckaroo(Order $order): bool
    {
        $payment = $order->getPayment();
        $transactionKey = (string)($payment->getAdditionalInformation('buckaroo_original_transaction_key')
            ?: $payment->getLastTransId());

        if ($transactionKey === '') {
            return false;
        }

        try {
            $status = $this->getAdapter()->getTransactionStatus(
                str_replace('buckaroo_magento2_', '', (string)$payment->getMethod()),
                (int)$order->getStoreId(),
                $transactionKey
            );
        } catch (\Throwable $th) {
            // Unknown: do not cancel what may be paid
            $this->logger->addError(__METHOD__ . ' ' . $th->getMessage());
            return true;
        }

        return in_array((int)$status->getStatusCode(), self::PAID_OR_PROCESSING_STATUSES, true);
    }

    /**
     * Get cancel guard
     *
     * @return CancelGuard
     */
    private function getCancelGuard(): CancelGuard
    {
        return $this->cancelGuard ??= ObjectManager::getInstance()->get(CancelGuard::class);
    }

    /**
     * Get adapter
     *
     * @return SdkAdapter
     */
    private function getAdapter(): SdkAdapter
    {
        return $this->sdkAdapter ??= ObjectManager::getInstance()->get(SdkAdapter::class);
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
