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

class TransferPaymentMethodLogo implements OptionSourceInterface
{
    public const OPTION_GENERIC_BANK_LOGO = 'generic_bank_logo';
    public const OPTION_SEPA_CREDIT_TRANSFER = 'sepa_credit_transfer';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::OPTION_GENERIC_BANK_LOGO,
                'label' => __('Generic Bank Logo (Default)'),
            ],
            [
                'value' => self::OPTION_SEPA_CREDIT_TRANSFER,
                'label' => __('SEPA Credit Transfer Logo'),
            ],
        ];
    }
}
