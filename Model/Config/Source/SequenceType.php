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

class SequenceType implements OptionSourceInterface
{
    public const SEQUENCE_TYPE_ONEOFF = 1;
    public const SEQUENCE_TYPE_RECURRING = 0;

    /**
     * Return the sequence type options for the system configuration dropdown.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        $options[] = ['value' => self::SEQUENCE_TYPE_ONEOFF, 'label' => __('One-Off')];
        $options[] = ['value' => self::SEQUENCE_TYPE_RECURRING, 'label' => __('Recurring')];

        return $options;
    }
}
