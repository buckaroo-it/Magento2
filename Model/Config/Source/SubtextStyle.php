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

class SubtextStyle implements \Magento\Framework\Data\OptionSourceInterface
{
    public const ITALIC = 'italic';
    public const BOLD = 'bold';
    public const REGULAR = 'regular';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::REGULAR, 'label' => __('Regular')],
            ['value' => self::ITALIC, 'label' => __('Italic')],
            ['value' => self::BOLD, 'label' => __('Bold')]
        ];
    }
}
