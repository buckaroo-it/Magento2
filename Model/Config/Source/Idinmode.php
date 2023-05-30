<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
