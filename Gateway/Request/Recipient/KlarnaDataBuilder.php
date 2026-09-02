<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

class KlarnaDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * Gender value sent when no gender is collected from the customer.
     *
     * Klarna (MoR) no longer asks the shopper to select a gender, but the
     * parameter is mandatory for Buckaroo. We therefore always send "unknown".
     */
    private const GENDER_UNKNOWN = 'unknown';

    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        return [
            'recipient' => [
                'gender'    => $this->getGender(),
                'firstName' => $this->getFirstname(),
                'lastName'  => $this->getLastName(),
                'birthDate' => $this->getBirthDate(),
            ],
        ];
    }

    /**
     * Klarna (MoR) does not collect a gender from the shopper.
     *
     * The gender parameter is mandatory for Buckaroo, so we always send the
     * neutral value "unknown" instead of showing an extra selection step.
     *
     * @return string
     */
    protected function getGender(): string
    {
        return self::GENDER_UNKNOWN;
    }

    /**
     * @inheritdoc
     */
    protected function getFormatDate(): string
    {
        return 'Y-m-d';
    }
}
