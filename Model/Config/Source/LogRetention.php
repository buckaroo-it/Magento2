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

class LogRetention implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => (60 * 60 * 24), 'label' => __('Day')],
            ['value' => (60 * 60 * 24 * 7), 'label' => __('Week')],
            ['value' => (60 * 60 * 24 * 31), 'label' => __('Month')],
            ['value' => (60 * 60 * 24 * 31 * 12), 'label' => __('Year')],
        ];
    }
}
