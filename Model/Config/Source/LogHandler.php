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

class LogHandler implements OptionSourceInterface
{
    public const TYPE_FILES = 1;
    public const TYPE_DB = 2;
    public const TYPE_BOTH = 3;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('File')],
            ['value' => 2, 'label' => __('Database')],
            ['value' => 3, 'label' => __('Both (File + Database)')],
        ];
    }
}
