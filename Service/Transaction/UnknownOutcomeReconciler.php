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

namespace Buckaroo\Magento2\Service\Transaction;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Settles orders that were placed pending because Buckaroo's answer to the payment was lost.
 *
 * Normally Buckaroo's push does this. Buckaroo stops retrying a push after a few minutes, so orders
 * that are still waiting are checked against Buckaroo:
 * - no transaction on the invoice, or only failed ones: the shopper was not charged, cancel;
 * - a successful one without a push: comment once so the merchant can resend the push from Plaza;
 * - pending: keep waiting.
 */
class UnknownOutcomeReconciler
{
    /** Leave the push time to arrive before looking (minutes) */
    private const MIN_AGE_MINUTES = 10;

    /** Cancel when Buckaroo still has no transaction after this many minutes */
    private const CANCEL_IF_NOTHING_AFTER_MINUTES = 30;

    /** Comment on a paid order whose push did not arrive after this many minutes */
    private const PAID_WITHOUT_PUSH_AFTER_MINUTES = 120;

    /** Stop looking at orders older than this (days) */
    private const MAX_AGE_DAYS = 7;

    private const BATCH_SIZE = 200;

    /** Payment flag: the merchant was told about a payment without a push */
    public const NOTED_PAID_WITHOUT_PUSH = 'buckaroo_outcome_paid_without_push_noted';

    private const SUCCESS = 190;
    private const FAILED = [490, 491, 492, 690, 890, 891];

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $orderCollectionFactory;

    /**
     * @var BuckarooAdapter
     */
    private BuckarooAdapter $adapter;

    /**
     * @var CancelGuard
     */
    private CancelGuard $cancelGuard;

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
     * @param CollectionFactory        $orderCollectionFactory
     * @param BuckarooAdapter          $adapter
     * @param CancelGuard              $cancelGuard
     * @param OrderRepositoryInterface $orderRepository
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        BuckarooAdapter $adapter,
        CancelGuard $cancelGuard,
        OrderRepositoryInterface $orderRepository,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->adapter = $adapter;
        $this->cancelGuard = $cancelGuard;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Execute
     *
     * @param int|null $now unix time, for tests
     *
     * @return void
     */
    public function execute(?int $now = null): void
    {
        $now = $now ?? time();

        foreach ($this->getWaitingOrders($now) as $order) {
            try {
                $this->reconcile($order, $now);
            } catch (\Throwable $e) {
                $this->log($order, 'reconcile failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reconcile
     *
     * @param Order $order
     * @param int   $now
     *
     * @return void
     */
    public function reconcile(Order $order, int $now): void
    {
        $ageMinutes = $this->getMinutesWaiting($order, $now);
        if ($ageMinutes === null || $ageMinutes < self::MIN_AGE_MINUTES) {
            return;
        }

        $payment = $order->getPayment();
        $transactions = $this->adapter->getTransactionsByInvoice(
            str_replace('buckaroo_magento2_', '', (string)$payment->getMethod()),
            (int)$order->getStoreId(),
            (string)$order->getIncrementId()
        );
        $statuses = array_map(fn(array $transaction) => (int)$transaction['status'], $transactions);

        if (in_array(self::SUCCESS, $statuses, true)) {
            if ($ageMinutes >= self::PAID_WITHOUT_PUSH_AFTER_MINUTES) {
                $this->notePaidWithoutPush($order, $transactions);
            }
            return;
        }

        $comment = $this->getCancelReason($statuses, $ageMinutes);
        if ($comment !== null) {
            $this->cancel($order, $comment);
        }
    }

    /**
     * Minutes since the answer was lost, or null when the order is not waiting for Buckaroo
     *
     * @param Order $order
     * @param int   $now
     *
     * @return int|null
     */
    private function getMinutesWaiting(Order $order, int $now): ?int
    {
        $payment = $order->getPayment();
        $since = $payment ? $payment->getAdditionalInformation(CancelGuard::OUTCOME_UNKNOWN) : null;

        return $since ? (int)floor(($now - (int)$since) / 60) : null;
    }

    /**
     * Why the order can be cancelled, or null when it has to keep waiting
     *
     * @param int[] $statuses
     * @param int   $ageMinutes
     *
     * @return \Magento\Framework\Phrase|null
     */
    private function getCancelReason(array $statuses, int $ageMinutes)
    {
        if ($statuses !== [] && array_diff($statuses, self::FAILED) === []) {
            return __('Buckaroo reports the payment as failed. The order was cancelled.');
        }

        if ($statuses === [] && $ageMinutes >= self::CANCEL_IF_NOTHING_AFTER_MINUTES) {
            return __('Buckaroo has no payment for this order. The order was cancelled.');
        }

        return null;
    }

    /**
     * Get waiting orders
     *
     * @param int $now
     *
     * @return Order[]
     */
    private function getWaitingOrders(int $now): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('main_table.state', ['in' => [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]])
            ->addFieldToFilter('main_table.created_at', ['gt' => date('Y-m-d H:i:s', $now - self::MAX_AGE_DAYS * 86400)])
            ->addFieldToFilter('main_table.created_at', ['lt' => date('Y-m-d H:i:s', $now - self::MIN_AGE_MINUTES * 60)])
            ->setPageSize(self::BATCH_SIZE);

        $collection->getSelect()
            ->join(
                ['buckaroo_payment' => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = buckaroo_payment.parent_id',
                []
            )
            ->where('buckaroo_payment.additional_information LIKE ?', '%"' . CancelGuard::OUTCOME_UNKNOWN . '"%')
            // Already reported to the merchant: nothing left to do, keep them from filling the batch
            ->where('buckaroo_payment.additional_information NOT LIKE ?', '%"' . self::NOTED_PAID_WITHOUT_PUSH . '"%')
            ->order('main_table.created_at DESC');

        return $collection->getItems();
    }

    /**
     * Cancel the order under the order lock, if nothing says it is paid
     *
     * @param Order                     $order
     * @param \Magento\Framework\Phrase $comment
     *
     * @return void
     */
    private function cancel(Order $order, $comment): void
    {
        $this->cancelGuard->runLocked((int)$order->getId(), 'reconcile unknown outcome', function (Order $fresh) use ($comment) {
            // Buckaroo answered: the outcome is known now
            $fresh->getPayment()->unsAdditionalInformation(CancelGuard::OUTCOME_UNKNOWN);

            $blocker = $this->cancelGuard->getCancelBlocker($fresh);
            if ($blocker !== null) {
                $this->orderRepository->save($fresh);
                $this->log($fresh, 'not cancelling: ' . $blocker);
                return false;
            }

            $fresh->addCommentToStatusHistory($comment);
            $fresh->cancel();
            $this->orderRepository->save($fresh);
            $this->log($fresh, 'cancelled');

            return true;
        });
    }

    /**
     * Note paid without push
     *
     * @param Order $order
     * @param array $transactions
     *
     * @return void
     */
    private function notePaidWithoutPush(Order $order, array $transactions): void
    {
        $payment = $order->getPayment();
        if ($payment->getAdditionalInformation(self::NOTED_PAID_WITHOUT_PUSH)) {
            return;
        }

        $paidKeys = array_column(array_filter($transactions, fn($t) => (int)$t['status'] === self::SUCCESS), 'key');

        $order->addCommentToStatusHistory(__(
            'Buckaroo reports this order as paid (transaction %1) but its confirmation never arrived. Resend'
            . ' the push from Buckaroo Plaza, or invoice the order once you have checked the payment.',
            implode(', ', $paidKeys)
        ));
        $payment->setAdditionalInformation(self::NOTED_PAID_WITHOUT_PUSH, 1);
        $this->orderRepository->save($order);
        $this->log($order, 'paid at Buckaroo without a push, merchant notified');
    }

    /**
     * Log
     *
     * @param Order  $order
     * @param string $message
     *
     * @return void
     */
    private function log(Order $order, string $message): void
    {
        $this->logger->addDebug(sprintf(
            '[RECONCILE] | [%s] - %s | order: %s',
            __METHOD__,
            $message,
            $order->getIncrementId()
        ));
    }
}
