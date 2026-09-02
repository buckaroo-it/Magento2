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
class ExpireDays extends Value
{
    /**
     * Test that the value is a integer within 0 and 180 interval
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    public function save()
    {
        $value = (int)$this->getValue();

        if (empty($value)) {
            return parent::save();
        }

        if (!is_int($value) || $value < 0 || $value > 180) {
            throw new LocalizedException(
                __("Please enter a valid integer within 0 and 180 interval")
            );
        }

        $this->setValue($value);

        return parent::save();
    }
}
