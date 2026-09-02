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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\Service\Order\CancelRemainingReservation;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Release the uncaptured part of a Klarna reservation when the last open order lines are
 * cancelled individually.
 *
 * A partially shipped order is captured for the shipped lines only, after which a fulfilment
 * system typically cancels the lines it could not ship instead of cancelling the whole order.
 * That never reaches order_cancel_after, so without this observer the remainder stays reserved
 * at Klarna.
 */
class CancelReservationOnItemCancel implements ObserverInterface
{
    /**
     * @var CancelRemainingReservation
     */
    private CancelRemainingReservation $cancelRemainingReservation;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param CancelRemainingReservation      $cancelRemainingReservation
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param BuckarooLoggerInterface         $logger
     */
    public function __construct(
        CancelRemainingReservation $cancelRemainingReservation,
        OrderPaymentRepositoryInterface $paymentRepository,
        BuckarooLoggerInterface $logger
    ) {
        $this->cancelRemainingReservation = $cancelRemainingReservation;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
    }

    /**
     * Cancel the remaining reservation once this item cancellation empties the order.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Item|null $item */
        $item = $observer->getEvent()->getItem();
        if ($item === null) {
            return;
        }

        $order = $item->getOrder();
        if (!$this->isKlarnaReservationOrder($order)) {
            return;
        }

        // Nothing captured yet means the order is being cancelled as a whole, which the
        // standard cancel path already reports to Buckaroo.
        if (!$order->hasInvoices()) {
            return;
        }

        $remaining = $this->getQtyToInvoiceAfterCancel($order, $item);
        if ($remaining > 0) {
            $this->logger->addDebug(sprintf(
                '[KLARNA] | [Observer] | [%s:%s] - Order %s still has %.2f qty to invoice after '
                . 'cancelling item %s; keeping the reservation open.',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $remaining,
                (string)$item->getSku()
            ));

            return;
        }

        if (!$this->cancelRemainingReservation->execute($order)) {
            return;
        }

        $this->paymentRepository->save($order->getPayment());
    }

    /**
     * Whether the order is paid with a Klarna method that holds a reservation.
     *
     * @param Order|null $order
     *
     * @return bool
     */
    private function isKlarnaReservationOrder(?Order $order): bool
    {
        $payment = $order !== null ? $order->getPayment() : null;
        if ($payment === null) {
            return false;
        }

        return in_array((string)$payment->getMethod(), [Klarna::CODE, Klarnakp::CODE], true);
    }

    /**
     * Quantity still open for invoicing once this cancellation is applied.
     *
     * The event is dispatched before Magento writes qty_canceled, so the quantity the item is
     * about to cancel is subtracted here. Items cancelled earlier in the same run already carry
     * their qty_canceled.
     *
     * @param Order $order
     * @param Item  $cancelledItem
     *
     * @return float
     */
    private function getQtyToInvoiceAfterCancel(Order $order, Item $cancelledItem): float
    {
        $remaining = 0.0;

        foreach ($order->getAllItems() as $item) {
            if ($item->isDummy()) {
                continue;
            }

            $qtyToInvoice = (float)$item->getQtyToInvoice();

            if ((int)$item->getId() === (int)$cancelledItem->getId()) {
                $qtyToInvoice -= (float)$item->getQtyToCancel();
            }

            if ($qtyToInvoice > 0) {
                $remaining += $qtyToInvoice;
            }
        }

        return $remaining;
    }
}
