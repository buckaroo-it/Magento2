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

class Enablemode implements OptionSourceInterface
{
    public const ENABLE_OFF = 0;
    public const ENABLE_TEST = 1;
    public const ENABLE_LIVE = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => __('Off')],
            ['value' => 1, 'label' => __('Test')],
            ['value' => 2, 'label' => __('Live')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            0 => __('Off'),
            1 => __('Test'),
            2 => __('Live')
        ];
    }
}
