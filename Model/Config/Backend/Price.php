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

class Price extends Value
{
    /**
     * Validate that the number is a valid price.
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    public function save()
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $value = $this->getValue();
        if (!empty($value) && !is_numeric($value)) {
            throw new LocalizedException(__("Please enter a valid number: '%1'.", $value));
        }

        return parent::save();
    }
}
