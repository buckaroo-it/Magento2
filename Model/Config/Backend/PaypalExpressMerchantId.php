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

class PaypalExpressMerchantId extends Value
{
    /**
     * Save merchant id
     *
     * @throws \Exception
     */
    public function save()
    {
        if (is_array($this->getFieldsetDataValue('available_buttons')) &&
            strlen($this->getValue()) === 0
        ) {
            throw new LocalizedException(__('Paypal express merchant id is required'));
        }

        return parent::save();
    }
}
