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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\SecondChance;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Finds the orders a customer placed after abandoning one, which decide whether a reminder still makes sense.
 *
 * "After" is measured from the abandoned order's own created_at, not from its SecondChance record. A record is
 * written when its order is saved as pending_payment or canceled, which can be long after placement. On browser
 * back it is written while the follow-up order is being placed (GatewayCommand cancels the previous order before
 * the new one is inserted), so both carry the same second.
 *
 * An unpaid follow-up only postpones the reminder when it is the replacement checkout for this abandoned order
 * (payment additional information buckaroo_cancel_order_id). Matching any newer pending order for the same e-mail
 * blocked reminders on shared QA addresses and leftover pending_payment orders.
 */
class FollowUpOrderFinder
{
    private const PAID_STATES = [Order::STATE_PROCESSING, Order::STATE_COMPLETE];

    private const AWAITING_PAYMENT_STATES = [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT];

    /**
     * How many recent unpaid candidates to inspect for a linked replacement checkout.
     */
    private const AWAITING_PAYMENT_CANDIDATE_LIMIT = 20;

    /**
     * Payment additional information key set when a restored quote cancels the previous pending order.
     *
     * @see \Buckaroo\Magento2\Observer\RestoreQuote
     */
    private const CANCEL_PREVIOUS_ORDER_KEY = 'buckaroo_cancel_order_id';

    /**
     * @var CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @param CollectionFactory $orderCollectionFactory
     * @param DateTime          $dateTime
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        DateTime $dateTime
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->dateTime = $dateTime;
    }

    /**
     * Get a paid order the customer placed at or after the abandoned one.
     *
     * @param string         $customerEmail
     * @param OrderInterface $abandonedOrder
     * @return OrderInterface|null
     */
    public function getPaidOrder(string $customerEmail, OrderInterface $abandonedOrder): ?OrderInterface
    {
        return $this->getFirstOrder(
            $this->createFollowUpCollection($customerEmail, $abandonedOrder)
                ->addFieldToFilter('state', ['in' => self::PAID_STATES])
                ->setPageSize(1)
        );
    }

    /**
     * Get the replacement checkout for the abandoned order that is still waiting for its payment result.
     *
     * Only an order younger than the grace period counts. An older one is not going to be paid any more, and
     * waiting for it would hold back the reminder until the record is pruned.
     *
     * @param string         $customerEmail
     * @param OrderInterface $abandonedOrder
     * @param int            $graceSeconds
     * @return OrderInterface|null
     */
    public function getOrderAwaitingPayment(
        string $customerEmail,
        OrderInterface $abandonedOrder,
        int $graceSeconds
    ): ?OrderInterface {
        $abandonedId = (int) $abandonedOrder->getEntityId();
        if ($abandonedId <= 0) {
            return null;
        }

        $placedSince = $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - $graceSeconds);

        $collection = $this->createFollowUpCollection($customerEmail, $abandonedOrder)
            ->addFieldToFilter('state', ['in' => self::AWAITING_PAYMENT_STATES])
            ->addFieldToFilter('created_at', ['gteq' => $placedSince])
            ->setPageSize(self::AWAITING_PAYMENT_CANDIDATE_LIMIT);

        foreach ($collection as $order) {
            if ($this->isReplacementCheckoutForAbandonedOrder($order, $abandonedId)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Create a collection of the customer's other orders placed at or after the abandoned one.
     *
     * @param string         $customerEmail
     * @param OrderInterface $abandonedOrder
     * @return Collection
     */
    private function createFollowUpCollection(string $customerEmail, OrderInterface $abandonedOrder): Collection
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_email', $customerEmail)
            ->addFieldToFilter('entity_id', ['neq' => $abandonedOrder->getEntityId()])
            ->addFieldToFilter('created_at', ['gteq' => $abandonedOrder->getCreatedAt()]);

        return $collection;
    }

    /**
     * True when this order was placed after browser-back / restore-quote cancelled the abandoned one.
     *
     * @param OrderInterface $order
     * @param int            $abandonedId
     * @return bool
     */
    private function isReplacementCheckoutForAbandonedOrder(OrderInterface $order, int $abandonedId): bool
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return false;
        }

        $cancelOrderId = $payment->getAdditionalInformation(self::CANCEL_PREVIOUS_ORDER_KEY);

        return $cancelOrderId !== null && (int) $cancelOrderId === $abandonedId;
    }

    /**
     * Get the first order of a collection, or null when it is empty.
     *
     * @param Collection $collection
     * @return OrderInterface|null
     */
    private function getFirstOrder(Collection $collection): ?OrderInterface
    {
        $order = $collection->getFirstItem();

        return $order instanceof OrderInterface && $order->getId() ? $order : null;
    }
}
