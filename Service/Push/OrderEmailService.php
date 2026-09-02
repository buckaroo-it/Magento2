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

namespace Buckaroo\Magento2\Service\Push;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class OrderEmailService
{
    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @param OrderSender   $orderSender
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    ) {
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * Send an order confirmation email to the customer.
     *
     * @param Order $order
     * @param bool  $forceSyncMode
     *
     * @return bool
     */
    public function sendOrderEmail(Order $order, bool $forceSyncMode = false): bool
    {
        return $this->orderSender->send($order, $forceSyncMode);
    }

    /**
     * Send invoice email to the customer.
     *
     * @param Invoice $invoice
     * @param bool    $forceSyncMode
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function sendInvoiceEmail(Invoice $invoice, bool $forceSyncMode = false): bool
    {
        return $this->invoiceSender->send($invoice, $forceSyncMode);
    }
}
