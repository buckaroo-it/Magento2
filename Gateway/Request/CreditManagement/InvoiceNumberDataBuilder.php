<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class InvoiceNumberDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {

        parent::initialize($buildSubject);
        return [
            'invoice' => $this->getOrder()->getIncrementId() . '-creditnote'
        ];
    }
}
