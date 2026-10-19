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

/**
 * Call to action shown on the Apple Pay button (the `type` attribute of <apple-pay-button>).
 *
 * The default "buy" is what every Apple Pay button renders today, so installations that never
 * touch the setting see no change. Apple's donate, contribute, support and tip types are
 * deliberately not offered. Labels are unique keys so the module's translations cannot
 * retranslate other modules' strings; the admin preview shows Apple's localised button text.
 */
class ApplepayButtonType implements OptionSourceInterface
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
     * Apple `type` value => admin label (literal phrases so i18n:collect-phrases finds them)
     *
     * @return array
     */
    private function getLabels(): array
    {
        return [
            'buy'       => __('Buy (Apple Pay)'),
            'check-out' => __('Check out (Apple Pay)'),
            'order'     => __('Order (Apple Pay)'),
            'pay'       => __('Pay (Apple Pay)'),
            'book'      => __('Book (Apple Pay)'),
            'subscribe' => __('Subscribe (Apple Pay)'),
            'continue'  => __('Continue (Apple Pay)'),
            'rent'      => __('Rent (Apple Pay)'),
            'set-up'    => __('Set up (Apple Pay)'),
            'reload'    => __('Reload (Apple Pay)'),
            'add-money' => __('Add money (Apple Pay)'),
            'top-up'    => __('Top up (Apple Pay)'),
            'plain'     => __('Logo only (Apple Pay)'),
        ];
    }
}
