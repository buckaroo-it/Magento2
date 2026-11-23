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

namespace Buckaroo\Magento2\Test\Unit\Stubs;

use Magento\Framework\DataObject;

/**
 * Minimal payment info: plain data plus the real setAdditionalInformation($key, $value) contract.
 */
class PaymentInfoStub extends DataObject
{
    /**
     * Set one additional information entry
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setAdditionalInformation($key, $value = null)
    {
        $info = (array)$this->getData('additional_information');
        $info[$key] = $value;
        return $this->setData('additional_information', $info);
    }
}
