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
