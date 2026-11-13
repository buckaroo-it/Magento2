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

class LogoColors implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Magenta / with border — for light or white backgrounds.
     * Maps to: snel-bestellen-reg-magenta.svg (Reg_Magenta_Border)
     */
    public const MAGENTA_OPTION = 'Magenta';

    /**
     * White / no border — for dark or colored backgrounds.
     * Maps to: snel-bestellen-reg-white.svg (Reg_No_Border)
     */
    public const WHITE_OPTION = 'White';

    /**
     * Return available logo color options as array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::MAGENTA_OPTION, 'label' => __('Magenta / With Border (for light backgrounds)')],
            ['value' => self::WHITE_OPTION,   'label' => __('White / No Border (for dark backgrounds)')],
        ];
    }
}
