<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;

class ApplePayFormatData implements FormatFormDataInterface
{
    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var ShippingAddressRequestInterfaceFactory
     */
    private $shippingAddrRequestFactory;

    /**
     * @param DataObjectFactory $dataObjectFactory
     * @param ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
     */
    public function __construct(
        DataObjectFactory                      $dataObjectFactory,
        ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingAddrRequestFactory = $shippingAddrRequestFactory;
    }

    /**
     * Get Product Object By Request
     *
     * @param array $productData
     * @return DataObject
     * @throws AddProductException
     */
    public function getProductObject(array $productData): DataObject
    {
        if (!isset($productData['id'])) {
            throw new AddProductException("A product is required", 1);
        }

        return $this->dataObjectFactory->create(
            ['data' => [
                'product' => $productData['id'],
                'selected_configurable_option' => '',
                'related_product' => '',
                'item' => $productData['id'],
                'super_attribute' => $productData['selected_options'] ?? '',
                'qty' => $productData['qty'],
            ]]
        );
    }

    /**
     * Get Product Object By Request
     *
     * @param array $addressData
     * @return ShippingAddressRequestInterface
     * @throws ExpressMethodsException
     */
    public function getShippingAddressObject(array $addressData): ShippingAddressRequestInterface
    {
        /** @var  ShippingAddressRequest $shippingAddressRequest */
        $shippingAddressRequest = $this->shippingAddrRequestFactory->create();

        $shippingAddressRequest->setCountryCode(
            isset($addressData['countryCode']) ? strtoupper($addressData['countryCode']) : 'NL'
        );
        $shippingAddressRequest->setPostalCode($addressData['postalCode']);
        $shippingAddressRequest->setCity($addressData['locality']);
        $shippingAddressRequest->setState($addressData['administrativeArea'] ?? 'unknown');

        return $shippingAddressRequest;
    }
}
