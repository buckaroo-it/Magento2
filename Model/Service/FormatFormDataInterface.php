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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Magento\Framework\DataObject;

interface FormatFormDataInterface
{
    /**
     * Get Product Object By Request
     *
     * @param array $productData
     *
     * @return DataObject
     */
    public function getProductObject(array $productData): DataObject;

    /**
     * Get Shipping Address Object By Request
     *
     * @param array $addressData
     *
     * @return ShippingAddressRequestInterface
     */
    public function getShippingAddressObject(array $addressData): ShippingAddressRequestInterface;
}
