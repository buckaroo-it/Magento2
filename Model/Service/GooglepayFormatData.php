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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;

class GooglepayFormatData implements FormatFormDataInterface
{
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ShippingAddressRequestInterfaceFactory
     */
    private $shippingAddrRequestFactory;

    /**
     * @param DataObjectFactory                      $dataObjectFactory
     * @param ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
     */
    public function __construct(
        DataObjectFactory $dataObjectFactory,
        ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingAddrRequestFactory = $shippingAddrRequestFactory;
    }

    /**
     * Get Product Object By Request
     *
     * @param array $productData
     *
     * @throws AddProductException
     *
     * @return DataObject
     */
    public function getProductObject(array $productData): DataObject
    {
        if (!isset($productData['id'])) {
            throw new AddProductException("A product is required", 1);
        }

        return $this->dataObjectFactory->create(
            [
                'data' => [
                    'product'                      => $productData['id'],
                    'selected_configurable_option' => '',
                    'related_product'              => '',
                    'item'                         => $productData['id'],
                    'super_attribute'              => $productData['selected_options'] ?? '',
                    'qty'                          => $productData['qty'],
                ]
            ]
        );
    }

    /**
     * Get Shipping Address Object from Google Pay wallet data
     *
     * @param array $addressData
     *
     * @throws ExpressMethodsException
     *
     * @return ShippingAddressRequestInterface
     */
    public function getShippingAddressObject(array $addressData): ShippingAddressRequestInterface
    {
        $shippingAddressRequest = $this->shippingAddrRequestFactory->create();

        $shippingAddressRequest->setCountryCode(
            isset($addressData['countryCode']) ? strtoupper($addressData['countryCode']) : 'NL'
        );

        $shippingAddressRequest->setPostalCode($addressData['postalCode'] ?? '');
        $shippingAddressRequest->setCity($addressData['locality'] ?? '');
        $shippingAddressRequest->setState(
            isset($addressData['administrativeArea']) && $addressData['administrativeArea']
            ? $addressData['administrativeArea'] : ''
        );

        return $shippingAddressRequest;
    }
}
