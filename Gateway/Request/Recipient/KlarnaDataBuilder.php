<?php

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;

class KlarnaDataBuilder extends AbstractRecipientDataBuilder
{
    protected function buildData(): array
    {
        return [
            'category' => $this->getCategory(),
            'gender' => $this->getGender(),
            'firstName' => $this->getFirstname(),
            'lastName' => $this->getLastName(),
            'birthDate' => $this->getBirthDate()
        ];
    }

    protected function getFormatDate(): string
    {
        return 'Y-m-d';
    }
}
