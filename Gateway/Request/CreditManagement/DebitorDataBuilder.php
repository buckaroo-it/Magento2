<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class DebtorDataBuilder extends AbstractDataBuilder
{

    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        return [
            'code' => $this->getOrder()->getBillingAddress()->getEmail()
        ];
    }
}
