<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class CreditcardTypeDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['name' => $this->getPayment()->getAdditionalInformation('card_type')];
    }
}