<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace TIG\Buckaroo\Model\Config\Source\TaxClass;

/**
 * Class Calculation
 *
 * @package TIG\Buckaroo\Model\Config\Source\TaxClass
 */
class Calculation implements \Magento\Framework\Option\ArrayInterface
{
    /**#@+
     * Constants for calculation with or without taxes
     */
    const DISPLAY_TYPE_EXCLUDING_TAX = 1;
    const DISPLAY_TYPE_INCLUDING_TAX = 2;
    /**#@-*/

    /**
     * @var array
     */
    protected $options;

    /**
     * @return array
     */
    public function toOptionArray()
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
