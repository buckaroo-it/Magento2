<?php

namespace Buckaroo\Magento2\Api;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

interface AddressHandlerInterface
{
    public function handle(Order $order): Order;
}
