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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * @method mixed getValue
 */
class Number extends Value
{
    /**
     * Test that the value is a number and is positive.
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    public function save()
    {
        $value = $this->getValue();

        if (empty($value)) {
            return parent::save();
        }

        if (!ctype_digit($value) || $value < 0) {
            throw new LocalizedException(__("Please enter a valid number: '%1'.", $value));
        }

        return parent::save();
    }
}
