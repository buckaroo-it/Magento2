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
