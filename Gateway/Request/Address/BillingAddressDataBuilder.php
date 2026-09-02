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

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Exception;
use Magento\Sales\Model\Order\Address;

class BillingAddressDataBuilder extends AbstractAddressDataBuilder
{
    /**
     * Get Billing Address
     *
     * @throws Exception
     *
     * @return Address
     */
    protected function getAddress(): Address
    {
        return $this->getOrder()->getBillingAddress();
    }
}
