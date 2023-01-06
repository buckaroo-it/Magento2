<?php

namespace Buckaroo\Magento2\Api;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

interface ArticleHandlerInterface
{
    /**
     * Get Items Data from Order (authorize/order)
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return array
     */
    public function getOrderArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Invoiced (capture)
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return array
     */
    public function getInvoiceArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Creditmemo (refund)
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return array
     */
    public function getCreditMemoArticlesData(Order $order, InfoInterface $payment): array;
}
