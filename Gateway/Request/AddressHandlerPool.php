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
     * @param  Order                              $order
     * @throws Exception
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
