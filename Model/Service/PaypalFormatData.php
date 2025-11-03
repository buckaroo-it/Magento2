<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterfaceFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;

class PaypalFormatData implements FormatFormDataInterface
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
     * Get product data object
     *
     * @param array $productData
     *
     * @throws AddProductException
     *
     * @return DataObject
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

    /**
     * Get shipping address as a data object
     *
     * @param array $addressData
     *
     * @throws ExpressMethodsException
     *
     * @return ShippingAddressRequestInterface
     */
    public function getShippingAddressObject(array $addressData): ShippingAddressRequestInterface
    {
        /** @var  ShippingAddressRequest $shippingAddressRequest */
        $shippingAddressRequest = $this->shippingAddrRequestFactory->create();

        $shippingAddressRequest->setCountryCode(
            isset($addressData['countryCode']) ? strtoupper($addressData['countryCode']) : 'NL'
        );
        $shippingAddressRequest->setPostalCode($addressData['postalCode']);

        // Sanitize city to meet validation requirements (A-Z, a-z, 0-9, -, ', spaces only)
        $city = $addressData['locality'] ?? '';
        $sanitizedCity = $this->sanitizeCityName($city);
        $shippingAddressRequest->setCity($sanitizedCity);

        $shippingAddressRequest->setState($addressData['administrativeArea'] ?? 'unknown');

        return $shippingAddressRequest;
    }

    /**
     * Sanitize city name to meet Buckaroo validation requirements
     * Only allows A-Z, a-z, 0-9, -, ', spaces
     *
     * @param string $cityName
     *
     * @return string
     */
    private function sanitizeCityName(string $cityName): string
    {
        if (empty($cityName)) {
            return 'City';
        }

        // Remove any characters that are not A-Z, a-z, 0-9, -, ', or spaces
        $sanitized = preg_replace('/[^A-Za-z0-9\-\'\s]/', '', $cityName);

        // Remove extra spaces and trim
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));

        // If sanitization results in empty string, use a default value
        if (empty($sanitized)) {
            $sanitized = 'City';
        }

        return $sanitized;
    }
}
