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

namespace Buckaroo\Magento2\Model\Config\Source\TaxClass;

use Magento\Framework\Data\OptionSourceInterface;

class Calculation implements OptionSourceInterface
{
    /**#@+
     * Constants for calculation with or without taxes
     */
    public const DISPLAY_TYPE_EXCLUDING_TAX = 1;
    public const DISPLAY_TYPE_INCLUDING_TAX = 2;
    /**#@-*/

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
        }
        return $this->options;
    }
}
