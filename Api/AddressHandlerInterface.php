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

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

/**
 * Address Handler it is used to modify shipping Address based on specific shipping method
 */
interface AddressHandlerInterface
{
    /**
     * Handle shipping address by shipping methods
     *
     * @param Order                 $order
     * @param OrderAddressInterface $shippingAddress
     *
     * @return Order
     */
    public function handle(Order $order, OrderAddressInterface $shippingAddress): Order;
}
