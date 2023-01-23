<?php

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Magento\Sales\Model\Order\Address;

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
