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
