<?php

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;

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
