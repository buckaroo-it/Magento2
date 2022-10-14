<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class InvoiceDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {

        parent::initialize($buildSubject);
        return [
            'invoiceAmount'         => $this->getOrder()->getGrandTotal(),
            'invoiceAmountVAT'      => $this->getOrder()->getTaxAmount(),
            'invoiceDate'           => date('Y-m-d'),
        ];
    }
}
