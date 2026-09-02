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
 * @method mixed getValue()
 */
class AllowedIssuers extends Value
{
    /**
     * Validate that at least one issuer is selected
     *
     * @throws LocalizedException
     *
     * @return $this
     */
    public function save()
    {
        $value = (array)$this->getValue();

        // Filter out empty values
        $value = array_filter($value);

        if (empty($value)) {
            throw new LocalizedException(
                __('You must select at least one credit or debit card for the hosted fields to function properly.')
            );
        }

        return parent::save();
    }
}
