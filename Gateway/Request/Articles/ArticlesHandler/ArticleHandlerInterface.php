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
     * @param  Order         $order
     * @param  InfoInterface $payment
     * @return array
     */
    public function getOrderArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Invoiced (capture)
     *
     * @param  Order         $order
     * @param  InfoInterface $payment
     * @return array
     */
    public function getInvoiceArticlesData(Order $order, InfoInterface $payment): array;

    /**
     * Get Items Data from Creditmemo (refund)
     *
     * @param  Order         $order
     * @param  InfoInterface $payment
     * @return array
     */
    public function getCreditMemoArticlesData(Order $order, InfoInterface $payment): array;
}
