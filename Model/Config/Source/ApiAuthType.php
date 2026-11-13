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

class ApiAuthType implements OptionSourceInterface
{
    /**
     * Get options for API authentication type selection
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bearer', 'label' => __('Bearer Token (Authorization: Bearer {token})')],
            ['value' => 'api_key_header', 'label' => __('API Key Header (X-API-Key: {key})')],
            ['value' => 'basic', 'label' => __('Basic Auth (Authorization: Basic {credentials})')],
        ];
    }
}
