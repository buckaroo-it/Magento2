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

class Idinmode implements OptionSourceInterface
{
    public const IDINMODE_GLOBAL = 0;
    public const IDINMODE_PRODUCT = 1;
    public const IDINMODE_CATEGORY = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::IDINMODE_GLOBAL, 'label' => __('Global')],
            ['value' => self::IDINMODE_PRODUCT, 'label' => __('Per Product')],
            ['value' => self::IDINMODE_CATEGORY, 'label' => __('Per Category')],
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
            self::IDINMODE_GLOBAL   => __('Global'),
            self::IDINMODE_PRODUCT  => __('Per Product'),
            self::IDINMODE_CATEGORY => __('Per Category'),
        ];
    }
}
