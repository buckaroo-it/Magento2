<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;

class EmailAddressDataBuilder extends AbstractDataBuilder
{
    private string $addressType;

    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $address = $this->getAddress();

        return ['email' => $address->getEmail()];
    }

    private function getAddress()
    {
        return ($this->addressType == 'shipping')
            ? $this->getOrder()->getShippingAddress()
            : $this->getOrder()->getBillingAddress();
    }
}
