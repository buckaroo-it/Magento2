<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class CompanyDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        $billingAddress = $this->getOrder()->getBillingAddress();
        if($billingAddress === null) {
            return [];
        }
        
        return [
            'culture'       => strtolower($billingAddress->getCountryId()),
            'name'          => $billingAddress->getCompany()
        ];
    }

}
