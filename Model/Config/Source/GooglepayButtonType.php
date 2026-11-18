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

/**
 * Call to action shown on the Google Pay button (`buttonType` in the Google Pay ButtonOptions).
 *
 * The default "buy" is what every Google Pay button renders today, so installations that never
 * touch the setting see no change. Google's legacy "long" and "short" values are aliases for buy
 * and plain and are not offered. Labels are unique keys so the module's translations cannot
 * retranslate other modules' strings; Google renders the localised button text itself.
 */
class GooglepayButtonType implements OptionSourceInterface
{
    public const TYPE_DEFAULT = 'buy';

    /**
     * Return the selectable button types.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * Return the stored value if it is an offered type, otherwise the default.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function normalize($value): string
    {
        return is_string($value) && isset($this->getLabels()[$value]) ? $value : self::TYPE_DEFAULT;
    }

    /**
     * Google `buttonType` value => admin label (literal phrases so i18n:collect-phrases finds them)
     *
     * @return array
     */
    private function getLabels(): array
    {
        return [
            'buy'       => __('Buy with Google Pay'),
            'checkout'  => __('Checkout with Google Pay'),
            'order'     => __('Order with Google Pay'),
            'pay'       => __('Pay with Google Pay'),
            'book'      => __('Book with Google Pay'),
            'subscribe' => __('Subscribe with Google Pay'),
            'donate'    => __('Donate with Google Pay'),
            'plain'     => __('Google Pay logo only'),
        ];
    }
}
