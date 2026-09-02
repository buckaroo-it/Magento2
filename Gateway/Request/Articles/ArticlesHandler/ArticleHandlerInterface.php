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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

/**
 * Article handler by payment method to modify the articles data sent in ArticlesDataBuilder
 */
interface ArticleHandlerInterface
{
    /**
     * Get Items Data from Order (authorize/order)
     *
     * @param Order         $order
     * @param InfoInterface $payment
     *
     * @return array
     */
    public function getOrderArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Invoiced (capture)
     *
     * @param Order         $order
     * @param InfoInterface $payment
     *
     * @return array
     */
    public function getInvoiceArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Creditmemo (refund)
     *
     * @param Order         $order
     * @param InfoInterface $payment
     *
     * @return array
     */
    public function getCreditMemoArticlesData(Order $order, InfoInterface $payment): array;
}
