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

class PaypalButtonShape implements OptionSourceInterface
{
    public const SHAPE_RECTANGULAR = 0;
    public const SHAPE_ROUNDED = 1;

    /**
     * Return the supported PayPal Express button shapes.
     *
     * Values stay 0/1 so existing Yes/No config remains valid.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SHAPE_RECTANGULAR, 'label' => __('Rectangular')],
            ['value' => self::SHAPE_ROUNDED, 'label' => __('Rounded')],
        ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::SHAPE_RECTANGULAR => __('Rectangular'),
            self::SHAPE_ROUNDED => __('Rounded'),
        ];
    }
}
