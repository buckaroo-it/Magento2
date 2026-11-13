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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EmailProviderMethod implements OptionSourceInterface
{
    /**
     * Get options for email provider method selection
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'smtp', 'label' => __('SMTP Transport')],
            ['value' => 'api', 'label' => __('REST API')],
        ];
    }
}
