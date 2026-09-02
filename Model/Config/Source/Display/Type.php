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

namespace Buckaroo\Magento2\Model\Config\Source\Display;

use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    public const DISPLAY_TYPE_EXCLUDING_TAX = 1;
    public const DISPLAY_TYPE_INCLUDING_TAX = 2;
    public const DISPLAY_TYPE_BOTH = 3;

    /**
     * @var array|null
     */
    protected $options = null;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        if (!$this->options) {
            $this->options = [];
            $this->options[] = [
                'value' => self::DISPLAY_TYPE_EXCLUDING_TAX,
                'label' => __('Excluding Tax'),
            ];
            $this->options[] = [
                'value' => self::DISPLAY_TYPE_INCLUDING_TAX,
                'label' => __('Including Tax'),
            ];
            $this->options[] = [
                'value' => self::DISPLAY_TYPE_BOTH,
                'label' => __('Including and Excluding Tax'),
            ];
        }
        return $this->options;
    }
}
