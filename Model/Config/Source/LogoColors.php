<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\Magento2\Model\Config\Source;

class LogoColors implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Magenta / with border — for light or white backgrounds.
     * Maps to: snel-bestellen-reg-magenta.svg (Reg_Magenta_Border)
     */
    const MAGENTA_OPTION = 'Magenta';

    /**
     * White / no border — for dark or colored backgrounds.
     * Maps to: snel-bestellen-reg-white.svg (Reg_No_Border)
     */
    const WHITE_OPTION = 'White';

    /** @deprecated Use MAGENTA_OPTION */
    const LIGHT_OPTION = self::MAGENTA_OPTION;

    /** @deprecated Use WHITE_OPTION */
    const DARK_OPTION = self::WHITE_OPTION;

    public function toOptionArray()
    {
        return [
            ['value' => self::MAGENTA_OPTION, 'label' => __('Magenta / With Border (for light backgrounds)')],
            ['value' => self::WHITE_OPTION,   'label' => __('White / No Border (for dark backgrounds)')],
        ];
    }
}
