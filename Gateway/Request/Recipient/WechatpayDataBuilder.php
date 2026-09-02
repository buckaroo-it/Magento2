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

class WechatpayDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        return [
            'recipient' => [
                'firstName' => $this->getFirstname(),
                'lastName'  => $this->getLastName(),
            ],
        ];
    }
}
