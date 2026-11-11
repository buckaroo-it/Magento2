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

namespace Buckaroo\Magento2\Plugin\Sales\Order;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Magento\Sales\Model\Order;

/**
 * Block cancelling a Klarna order while shipped items are not invoiced.
 *
 * CancelReservation releases everything that is not captured yet, which would include the
 * shipped items. Once they are invoiced, the order can be cancelled as usual.
 */
class KeepShippedKlarnaReservationOpen
{
    /**
     * Refuse the cancel while shipped items are not invoiced.
     *
     * @param Order $subject
     * @param bool  $result
     *
     * @return bool
     */
    public function afterCanCancel(Order $subject, $result)
    {
        if (!$result || !$this->isKlarnaReservationOrder($subject)) {
            return $result;
        }

        return !$this->hasShippedUninvoicedItems($subject);
    }

    /**
     * Whether the order is paid with a Klarna method that holds a reservation.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isKlarnaReservationOrder(Order $order): bool
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return false;
        }

        return in_array((string)$payment->getMethod(), [Klarna::CODE, Klarnakp::CODE], true);
    }

    /**
     * Whether any item was shipped beyond what has been invoiced.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function hasShippedUninvoicedItems(Order $order): bool
    {
        foreach ($order->getAllItems() as $item) {
            if ($item->isDummy(true)) {
                continue;
            }

            if ((float)$item->getQtyShipped() - (float)$item->getQtyInvoiced() > 0.0001) {
                return true;
            }
        }

        return false;
    }
}
