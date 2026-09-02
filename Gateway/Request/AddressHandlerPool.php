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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Api\AddressHandlerInterface;
use Exception;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use TypeError;

class AddressHandlerPool
{
    /**
     * @var array|AddressHandlerInterface[]
     */
    protected $addressHandlers;

    /**
     * @param array $addressHandlers
     */
    public function __construct(array $addressHandlers)
    {
        foreach ($addressHandlers as $key => $addressHandler) {
            if (!($addressHandler instanceof AddressHandlerInterface)) {
                throw new TypeError("$key - $addressHandler is not instance of AddressHandlerInterface");
            }
        }
        $this->addressHandlers = $addressHandlers;
    }

    /**
     * Change shipping address based on Shipping method
     *
     * @param Order $order
     *
     * @throws Exception
     *
     * @return OrderAddressInterface|Address|null
     */
    public function getShippingAddress(Order $order)
    {
        try {
            $orderShippingAddress = $order->getShippingAddress() ?? $order->getBillingAddress();
            $shippingAddress = clone $orderShippingAddress;
            foreach ($this->addressHandlers as $addressHandler) {
                $order = $addressHandler->handle($order, $shippingAddress);
            }
        } catch (\Throwable $th) {
            throw new \Buckaroo\Magento2\Exception($th->getMessage(), 0, $th);
        }

        return $shippingAddress;
    }
}
