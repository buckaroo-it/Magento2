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

class Business implements OptionSourceInterface
{
    public const BUSINESS_B2C = 1;
    public const BUSINESS_B2B = 2;
    public const BUSINESS_BOTH = 3;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        // Business options
        $options[] = ['value' => self::BUSINESS_B2C, 'label' => __('B2C')];
        $options[] = ['value' => self::BUSINESS_B2B, 'label' => __('B2B')];
        $options[] = ['value' => self::BUSINESS_BOTH, 'label' => __('Both')];

        return $options;
    }
}
