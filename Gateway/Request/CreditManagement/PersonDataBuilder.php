<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class PersonDataBuilder extends AbstractDataBuilder
{

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $address = $this->getOrder()->getBillingAddress();

        if ($address === null) {
            return [];
        }

        return [
            'culture' =>  strtolower($address->getCountryId()),
            'name' => $address->getFirstname() . ' ' .$address->getLastname(),
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname()
        ];
    }
}
