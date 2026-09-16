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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;

/**
 * Single point of truth for the Klarna KP reservation number.
 *
 * The reservation number only reaches us on the push, roughly 25 seconds after the order was
 * created - the reserve itself answers 791 with no number. By then the order row exists and other
 * processes may already hold a copy of it. `Magento\Sales\Model\ResourceModel\EntityAbstract::
 * updateObject()` writes every column from the in-memory object with no changed-field diff, so any
 * full save of a stale order silently reverts the column and the capture can never be built again.
 *
 * The number is therefore written to three independent rows and read back in order of how well
 * each survives a foreign write:
 *
 *   1. the authorization transaction - its own table, nothing bulk-saves transaction rows;
 *   2. the payment's additional information;
 *   3. the `sales_order` column, kept for reporting and written with `saveAttribute()` so that we
 *      do not rewrite the whole row and clobber somebody else's column in turn.
 */
class ReservationNumberStore
{
    /**
     * Key used on the payment and on the transaction.
     */
    public const KEY = 'buckaroo_reservation_number';

    /**
     * @var OrderResource
     */
    private OrderResource $orderResource;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private TransactionRepositoryInterface $transactionRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param OrderResource                   $orderResource
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param TransactionRepositoryInterface  $transactionRepository
     * @param SearchCriteriaBuilder           $searchCriteriaBuilder
     * @param BuckarooLoggerInterface         $logger
     */
    public function __construct(
        OrderResource $orderResource,
        OrderPaymentRepositoryInterface $paymentRepository,
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderResource = $orderResource;
        $this->paymentRepository = $paymentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Persist the reservation number to every location, independently of each other.
     *
     * A failure to write one location must not cost us the others, so each write is tried on its
     * own and logged rather than thrown.
     *
     * @param Order  $order
     * @param string $reservationNumber
     *
     * @return void
     */
    public function save(Order $order, string $reservationNumber): void
    {
        $this->saveOnTransaction($order, $reservationNumber);
        $this->saveOnPayment($order, $reservationNumber);
        $this->saveOnOrder($order, $reservationNumber);
    }

    /**
     * Read the reservation number from the first location that still holds it.
     *
     * @param Order $order
     *
     * @return string|null
     */
    public function resolve(Order $order): ?string
    {
        // getData() rather than the magic getBuckarooReservationNumber(): the column is declared
        // in db_schema.xml, not on OrderInterface, so static analysis cannot see the accessor.
        $fromOrder = $order->getData(self::KEY);
        if (!empty($fromOrder) && is_scalar($fromOrder)) {
            return (string)$fromOrder;
        }

        $payment = $this->getPayment($order);
        $fromPayment = $payment !== null ? $payment->getAdditionalInformation(self::KEY) : null;
        if (!empty($fromPayment) && is_scalar($fromPayment)) {
            $this->logger->addWarning(sprintf(
                '[KLARNA_KP] | [%s:%s] - Reservation number for order %s was missing from the order '
                . 'but recovered from the payment. The order column was overwritten by another process.',
                __METHOD__,
                __LINE__,
                $order->getIncrementId()
            ));

            return (string)$fromPayment;
        }

        $fromTransaction = $this->readFromTransaction($order);
        if (!empty($fromTransaction)) {
            $this->logger->addWarning(sprintf(
                '[KLARNA_KP] | [%s:%s] - Reservation number for order %s was missing from the order '
                . 'and the payment, and was recovered from the authorization transaction.',
                __METHOD__,
                __LINE__,
                $order->getIncrementId()
            ));

            return $fromTransaction;
        }

        return null;
    }

    /**
     * Write the number onto the order, one column at a time.
     *
     * `saveAttribute()` issues an update for the named column only. A full save would rewrite every
     * column of `sales_order` from this in-memory copy, which is the very behaviour that loses the
     * number in the first place - we should not do it to anyone else.
     *
     * @param Order  $order
     * @param string $reservationNumber
     *
     * @return void
     */
    private function saveOnOrder(Order $order, string $reservationNumber): void
    {
        try {
            $order->setData(self::KEY, $reservationNumber);
            $this->orderResource->saveAttribute($order, self::KEY);
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_KP] | [%s:%s] - Could not store the reservation number on order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Write the number onto the payment's additional information.
     *
     * @param Order  $order
     * @param string $reservationNumber
     *
     * @return void
     */
    private function saveOnPayment(Order $order, string $reservationNumber): void
    {
        try {
            $payment = $this->getPayment($order);
            if ($payment === null) {
                return;
            }

            $payment->setAdditionalInformation(self::KEY, $reservationNumber);
            $this->paymentRepository->save($payment);
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_KP] | [%s:%s] - Could not store the reservation number on the payment of order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Write the number onto the authorization transaction.
     *
     * This is the copy that survives: transaction rows are appended by Magento and are not loaded
     * and re-saved in bulk by order integrations.
     *
     * @param Order  $order
     * @param string $reservationNumber
     *
     * @return void
     */
    private function saveOnTransaction(Order $order, string $reservationNumber): void
    {
        try {
            $transaction = $this->getAuthorizationTransaction($order);
            if ($transaction === null) {
                return;
            }

            $transaction->setAdditionalInformation(self::KEY, $reservationNumber);
            $this->transactionRepository->save($transaction);
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_KP] | [%s:%s] - Could not store the reservation number on the transaction of order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Read the number back from the authorization transaction.
     *
     * @param Order $order
     *
     * @return string|null
     */
    private function readFromTransaction(Order $order): ?string
    {
        try {
            $transaction = $this->getAuthorizationTransaction($order);
            if ($transaction === null) {
                return null;
            }

            $value = $transaction->getAdditionalInformation(self::KEY);

            return (empty($value) || !is_scalar($value)) ? null : (string)$value;
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_KP] | [%s:%s] - Could not read the reservation number from the transaction of order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $e->getMessage()
            ));

            return null;
        }
    }

    /**
     * The transaction row the reserve created at order placement.
     *
     * Klarna KP authorizes, so this is normally the `authorization` row. Not every flow writes
     * one, and we only need a durable row belonging to this order's payment, so the newest
     * transaction is an acceptable second choice.
     *
     * @param Order $order
     *
     * @return Transaction|null
     */
    private function getAuthorizationTransaction(Order $order): ?Transaction
    {
        if (!$order->getId()) {
            return null;
        }

        $authorization = $this->findTransaction((int)$order->getId(), TransactionInterface::TYPE_AUTH);

        return $authorization ?? $this->findTransaction((int)$order->getId(), null);
    }

    /**
     * Find a transaction for the order, optionally of a given type, newest first.
     *
     * @param int         $orderId
     * @param string|null $type
     *
     * @return Transaction|null
     */
    private function findTransaction(int $orderId, ?string $type): ?Transaction
    {
        $this->searchCriteriaBuilder->addFilter('order_id', $orderId);

        if ($type !== null) {
            $this->searchCriteriaBuilder->addFilter('txn_type', $type);
        }

        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)->create();
        $transactions = $this->transactionRepository->getList($searchCriteria)->getItems();
        $transaction = empty($transactions) ? null : reset($transactions);

        // TransactionInterface declares no-argument accessors; only the model carries the
        // keyed getAdditionalInformation()/setAdditionalInformation() pair we need.
        return $transaction instanceof Transaction ? $transaction : null;
    }

    /**
     * The order's payment, narrowed to the model that exposes keyed additional information.
     *
     * @param Order $order
     *
     * @return Payment|null
     */
    private function getPayment(Order $order): ?Payment
    {
        $payment = $order->getPayment();

        return $payment instanceof Payment ? $payment : null;
    }
}
