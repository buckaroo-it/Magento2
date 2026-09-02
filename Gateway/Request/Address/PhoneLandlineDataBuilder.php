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

namespace Buckaroo\Magento2\Gateway\Request\Address;

class PhoneLandlineDataBuilder extends PhoneDataBuilder
{
    /**
     * @inheritdoc
     */
    protected function returnPhoneDetails(string $telephone, string $landline = ''): array
    {
        return [
            'phone' => [
                'mobile'   => $telephone,
                'landline' => $landline
            ]
        ];
    }
}
