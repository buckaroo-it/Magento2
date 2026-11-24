<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

/**
 * Cancels an order only while holding the order lock the push uses, on a copy read from the database
 * after the lock was taken, and only when nothing says the order is (or may be) paid.
 *
 * A cancel that runs beside a successful push on an earlier copy of the order cancels a paid order.
 */
class CancelGuard
{
    /** Payment flag: a request reached Buckaroo but its outcome is not known yet */
    public const OUTCOME_UNKNOWN = 'buckaroo_outcome_unknown';

    /** Order states a Buckaroo order can be cancelled from */
    private const CANCELLABLE_STATES = [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT];

    /** Seconds to wait for the order lock */
    private const LOCK_WAIT = 5;

    /**
     * @var LockManagerWrapper
     */
    private LockManagerWrapper $lockManager;

    /**
     * @var OrderFactory
     */
    private OrderFactory $orderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * Constructor
     *
     * @param LockManagerWrapper       $lockManager
     * @param OrderFactory             $orderFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        LockManagerWrapper $lockManager,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        BuckarooLoggerInterface $logger
    ) {
        $this->lockManager = $lockManager;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Cancel the order if that is still safe
     *
     * @param int           $orderId
     * @param string        $reason       for the log
     * @param callable|null $beforeCancel receives the fresh order, runs under the lock before the cancel
     *
     * @return bool whether the order was cancelled
     */
    public function cancel(int $orderId, string $reason, ?callable $beforeCancel = null): bool
    {
        $result = $this->runLocked($orderId, $reason, function (Order $order) use ($reason, $beforeCancel) {
            $blocker = $this->getCancelBlocker($order);

            if ($blocker !== null) {
                $this->log((string)$order->getIncrementId(), $reason, 'not cancelling: ' . $blocker);
                return false;
            }

            if ($beforeCancel !== null) {
                $beforeCancel($order);
            }

            $order->cancel();
            $this->orderRepository->save($order);
            $this->log((string)$order->getIncrementId(), $reason, 'cancelled');

            return true;
        });

        return $result === true;
    }

    /**
     * Run $work with the order read from the database while holding the order lock
     *
     * @param int      $orderId
     * @param string   $reason for the log
     * @param callable $work   receives the fresh order
     *
     * @return mixed what $work returned, or null when the order or its lock is unavailable
     */
    public function runLocked(int $orderId, string $reason, callable $work)
    {
        $order = $this->load($orderId);
        if ($order === null) {
            return null;
        }

        $incrementId = (string)$order->getIncrementId();
        if (!$this->lockManager->lockOrder($incrementId, self::LOCK_WAIT)) {
            $this->log($incrementId, $reason, 'order lock not acquired');
            return null;
        }

        try {
            // Read again under the lock: a push may have changed the order while we waited
            $order = $this->load($orderId);

            return $order === null ? null : $work($order);
        } finally {
            $this->lockManager->unlockOrder($incrementId);
        }
    }

    /**
     * Why the order must not be cancelled, or null when it may be
     *
     * @param Order $order
     *
     * @return string|null
     */
    public function getCancelBlocker(Order $order): ?string
    {
        if ($order->hasInvoices()) {
            return 'the order is invoiced';
        }

        if ((float)$order->getTotalPaid() >= 0.01) {
            return 'the order is paid';
        }

        if (!in_array($order->getState(), self::CANCELLABLE_STATES, true)) {
            return sprintf('the order is in state "%s"', $order->getState());
        }

        $payment = $order->getPayment();
        if ($payment !== null && $payment->getAdditionalInformation(self::OUTCOME_UNKNOWN)) {
            return 'Buckaroo may have processed the payment, waiting for its confirmation';
        }

        if (!$order->canCancel()) {
            return 'Magento does not allow cancelling it';
        }

        return null;
    }

    /**
     * Load
     *
     * @param int $orderId
     *
     * @return Order|null
     */
    private function load(int $orderId): ?Order
    {
        $order = $this->orderFactory->create()->load($orderId);

        return $order->getId() ? $order : null;
    }

    /**
     * Log
     *
     * @param string $incrementId
     * @param string $reason
     * @param string $message
     *
     * @return void
     */
    private function log(string $incrementId, string $reason, string $message): void
    {
        $this->logger->addDebug(sprintf(
            '[CANCEL_ORDER] | [Service] | [%s] - %s | order: %s | reason: %s',
            __METHOD__,
            $message,
            $incrementId,
            $reason
        ));
    }
}
