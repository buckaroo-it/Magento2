<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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
