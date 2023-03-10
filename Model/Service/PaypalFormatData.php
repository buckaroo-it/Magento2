<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterfaceFactory;

class PaypalFormatData implements FormatFormDataInterface
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
        ShippingAddressRequestInterfaceFactory $shippingAddrRequestFactory,
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingAddrRequestFactory = $shippingAddrRequestFactory;
    }
    /**
     * @param array $productData
     * @return DataObject
     * @throws AddProductException
     */
    public function getProductObject(array $productData): DataObject
    {
        $data = [];

        foreach ($productData as $orderKeyValue) {
            $data[$orderKeyValue->getName()] = $orderKeyValue->getValue();
        }
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }

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
