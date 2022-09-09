<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Model\Order\Address;
use Buckaroo\Magento2\Gateway\Request\AbstractAddressDataBuilder;

class BillingAddressDataBuilder extends AbstractAddressDataBuilder
{
    /**
     * @return Address
     * @throws \Exception
     */
    protected function getAddress(): Address
    {
        return $this->getOrder()->getBillingAddress();
    }
}

