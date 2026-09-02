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

namespace Buckaroo\Magento2\Gateway\Response;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Keep the authorization open while the order still has lines left to capture.
 *
 * Magento refuses an online capture once the authorization transaction is closed
 * (Payment::canCapture()), so closing it after a PARTIAL capture makes every later shipment
 * register an invoice that is never paid and never reaches Buckaroo. The authorization is
 * therefore only closed when this capture leaves nothing to invoice.
 */
class CaptureTransactionIdHandler extends TransactionIdHandler
{
    /**
     * Close the authorization only when this capture was the final one.
     *
     * Invoice::register() registers the invoice items before it captures, so the order already
     * reflects this invoice when the response is handled.
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     */
    protected function shouldCloseParentTransaction(OrderPaymentInterface $payment): bool
    {
        $order = $payment instanceof Payment ? $payment->getOrder() : null;

        if ($order === null) {
            return true;
        }

        return !$order->canInvoice();
    }
}
