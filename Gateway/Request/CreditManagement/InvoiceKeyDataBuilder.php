<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class InvoiceKeyDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        return [
            'originalInvoiceNumber' => $this->getOrder()->getIncrementId(),
        ];
    }
}
