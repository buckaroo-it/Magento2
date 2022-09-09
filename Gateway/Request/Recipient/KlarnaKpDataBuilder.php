<?php

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\AbstractRecipientDataBuilder;

class KlarnaKpDataBuilder extends AbstractRecipientDataBuilder
{
    protected function buildData(): array
    {
        return [
            'firstName' => $this->getFirstname(),
            'lastName' => $this->getLastName(),
        ];
    }
}
