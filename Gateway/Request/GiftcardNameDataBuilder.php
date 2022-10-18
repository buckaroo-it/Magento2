<?php

namespace Buckaroo\Magento2\Gateway\Request;

class GiftcardNameDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return [
            'name' => $this->getPayment()->getAdditionalInformation('giftcard_method')
        ];
    }
}
