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

namespace Buckaroo\Magento2\Api;

use Magento\Sales\Model\Order;

/**
 * Interface for gift card refund operations
 * Senior developers always define interfaces for services
 */
interface GiftCardRefundServiceInterface
{
    /**
     * Refund gift cards for a cancelled order
     *
     * @param Order $order
     */
    public function refund(Order $order): void;
}
