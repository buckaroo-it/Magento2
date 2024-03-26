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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * @method mixed getValue
 */
class PaymentFee extends Value
{
    /**
     * Test that the value is a number and is positive.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function save()
    {
        $value = $this->getValue();
        $onlyLastChar = substr($value, -1);
        $withoutLastChar = substr($value, 0, -1);

        if (!empty($value) && !is_numeric(($onlyLastChar == '%' ? $withoutLastChar : $value))) {
            throw new LocalizedException(__("Please enter a valid number: '%1'.", $value));
        }

        return parent::save();
    }
}
