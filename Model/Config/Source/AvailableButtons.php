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

class AvailableButtons implements OptionSourceInterface
{
    public const BUTTON_PRODUCT = 'Product';
    public const BUTTON_CART = 'Cart';

    /**
     * Return the available express button placements.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        $options[] = ['value' => self::BUTTON_PRODUCT, 'label' => __('Product Page')];
        $options[] = ['value' => self::BUTTON_CART, 'label' => __('Cart')];

        return $options;
    }
}
