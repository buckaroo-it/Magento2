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

class PaypalButtonStyle implements OptionSourceInterface
{
    const COLOR_DEFAULT = 'gold';
    const COLOR_BLUE = 'blue';
    const COLOR_SILVER = 'silver';
    const COLOR_WHITE = 'white';
    const COLOR_BLACK = 'black';

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
