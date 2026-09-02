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

class PaypalButtonStyle implements OptionSourceInterface
{
    public const COLOR_DEFAULT = 'gold';
    public const COLOR_BLUE = 'blue';
    public const COLOR_SILVER = 'silver';
    public const COLOR_WHITE = 'white';
    public const COLOR_BLACK = 'black';

    /**
     * Return the supported PayPal button colors.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::COLOR_DEFAULT, 'label' => __('Gold (standard)')],
            ['value' => self::COLOR_BLUE, 'label' => __('Blue')],
            ['value' => self::COLOR_SILVER, 'label' => __('Silver')],
            ['value' => self::COLOR_WHITE, 'label' => __('White')],
            ['value' => self::COLOR_BLACK, 'label' => __('Black')],
        ];
    }
}
