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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SendInvoiceBy implements OptionSourceInterface
{
    public const ACTION_EMAIL = 'email';
    public const ACTION_MAIL = 'mail';

    /**
     * Return the available "send invoice by" options as an option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::ACTION_EMAIL,
                'label' => 'By e-mail'
            ],
            [
                'value' => self::ACTION_MAIL,
                'label' => 'By mail (Includes fee from Klarna)'
            ],
        ];
    }
}
