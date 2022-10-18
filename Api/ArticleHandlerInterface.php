<?php

namespace Buckaroo\Magento2\Api;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

interface ArticleHandlerInterface
{
    public function getOrderArticlesData(Order $order,  \Magento\Payment\Model\InfoInterface $payment): array;

    public function getInvoiceArticlesData(Order $order,  \Magento\Payment\Model\InfoInterface $payment): array;

    public function getCreditMemoArticlesData(Order $order,  \Magento\Payment\Model\InfoInterface $payment): array;
}
