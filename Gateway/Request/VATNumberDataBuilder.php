<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class VATNumberDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['vATNumber' => $this->getVatNumber()];
    }

    protected function getVatNumber()
    {
        return $this->getPayment()->getAdditionalInformation('customer_VATNumber') ?? '';
    }
}
