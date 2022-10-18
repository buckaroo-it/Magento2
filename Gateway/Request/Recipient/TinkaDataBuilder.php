<?php

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;

class TinkaDataBuilder extends AbstractRecipientDataBuilder
{
    protected function buildData(): array
    {
        return [
            'lastNamePrefix' => $this->getTitle(),
        ];
    }
}
