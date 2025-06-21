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

namespace Buckaroo\Magento2\Model\PaypalExpress;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

class OrderUpdate
{
    /**
     * @var stdClass|null
     */
    protected $responseAddressInfo;

    /**
     * @var BuckarooResponseDataInterface
     */
    private BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooResponseDataInterface $buckarooResponseData
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooResponseDataInterface $buckarooResponseData,
        BuckarooLoggerInterface $logger
    ) {
        $this->buckarooResponseData = $buckarooResponseData;
        $this->logger = $logger;
        $this->responseAddressInfo = $this->getAddressInfoFromPayRequest();
    }

    /**
     * Get payment response
     *
     * @return array|null
     */
    private function getAddressInfoFromPayRequest(): ?array
    {
        $buckarooResponse = $this->buckarooResponseData->getResponse()->toArray();
        if (!empty($buckarooResponse)
            && isset($buckarooResponse['Services']['Service']['ResponseParameter'])
        ) {
            return $this->formatAddressData($buckarooResponse['Services']['Service']['ResponseParameter']);
        }

        return null;
    }

    /**
     * Format address data in key/value pairs
     *
     * @param mixed $addressData
     * @return array
     */
    public function formatAddressData($addressData): array
    {
        $data = [];
        if (!is_array($addressData)) {
            return $data;
        }

        foreach ($addressData as $addressItem) {
            if (isset($addressItem->_) && isset($addressItem->Name)) {
                $data[$addressItem->Name] = $addressItem->_;
            }
        }
        return $data;
    }

    /**
     * Update order address with pay response data
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface
     * @return \Magento\Sales\Api\Data\OrderAddressInterface
     */
    public function updateAddress($address)
    {
        // Skip update if address is already properly filled (not "unknown")
        if ($address->getFirstname() !== 'unknown' && $address->getFirstname() !== 'Guest') {
            return $address;
        }

        $this->updateItem($address, OrderAddressInterface::FIRSTNAME, 'payerFirstname');
        $this->updateItem($address, OrderAddressInterface::LASTNAME, 'payerLastname');
        $this->updateItem($address, OrderAddressInterface::STREET, 'address_line_1');
        $this->updateItem($address, OrderAddressInterface::EMAIL, 'payerEmail');
        $this->updateItem($address, OrderAddressInterface::CITY, 'payerCity');
        $this->updateItem($address, OrderAddressInterface::COUNTRY_ID, 'payerCountry');
        $this->updateItem($address, OrderAddressInterface::POSTCODE, 'payerPostalCode');
        $this->updateItem($address, OrderAddressInterface::TELEPHONE, 'payerPhone');

        return $address;
    }

    protected function updateItem($address, $addressField, $responseField)
    {
        if ($this->valueExists($responseField)) {
            $value = $this->responseAddressInfo[$responseField];
            
            // Sanitize address data based on field type
            $value = $this->sanitizeAddressField($addressField, $value);
            
            $address->setData(
                $addressField,
                $value
            );
        }
    }

    /**
     * Sanitize address field data based on Magento validation requirements
     *
     * @param string $fieldType
     * @param string $value
     * @return string
     */
    private function sanitizeAddressField(string $fieldType, string $value): string
    {
        $originalValue = $value;
        
        switch ($fieldType) {
            case 'city':
                $value = $this->sanitizeCityName($value);
                break;
            case 'firstname':
            case 'lastname':
                $value = $this->sanitizePersonName($value);
                break;
            case 'telephone':
                $value = $this->sanitizePhoneNumber($value);
                break;
            case 'postcode':
                $value = $this->sanitizePostcode($value);
                break;
            default:
                $value = trim($value);
        }
        
        // Log if sanitization changed the value
        if ($originalValue !== $value) {
            $this->logger->addDebug(sprintf(
                'PayPal Express address data sanitized for field "%s": "%s" -> "%s"',
                $fieldType,
                $originalValue,
                $value
            ));
        }
        
        return $value;
    }

    /**
     * Sanitize person name (firstname/lastname)
     *
     * @param string $name
     * @return string
     */
    private function sanitizePersonName(string $name): string
    {
        // Remove special characters that might cause issues
        $sanitized = preg_replace('/[^\p{L}\p{M}\s\-\'\.]/u', '', $name);
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));
        
        if (empty($sanitized)) {
            $sanitized = 'Guest';
        }
        
        return $sanitized;
    }

    /**
     * Sanitize phone number
     *
     * @param string $phone
     * @return string
     */
    private function sanitizePhoneNumber(string $phone): string
    {
        // Keep numbers, spaces, dashes, parentheses, and plus sign
        $sanitized = preg_replace('/[^\d\s\-\(\)\+]/', '', $phone);
        $sanitized = trim($sanitized);
        
        if (empty($sanitized)) {
            $sanitized = '0000000000';
        }
        
        return $sanitized;
    }

    /**
     * Sanitize postcode
     *
     * @param string $postcode
     * @return string
     */
    private function sanitizePostcode(string $postcode): string
    {
        // Allow alphanumeric characters, spaces, and dashes
        $sanitized = preg_replace('/[^A-Za-z0-9\s\-]/', '', $postcode);
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));
        
        if (empty($sanitized)) {
            $sanitized = '00000';
        }
        
        return $sanitized;
    }

    /**
     * Sanitize city name to meet Magento validation requirements
     * Only allows A-Z, a-z, 0-9, -, ', spaces
     *
     * @param string $cityName
     * @return string
     */
    private function sanitizeCityName(string $cityName): string
    {
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

    private function valueExists($key): bool
    {
        return isset($this->responseAddressInfo[$key]) && is_string($this->responseAddressInfo[$key]);
    }

    /**
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    public function updateEmail(OrderInterface $order)
    {
        if ($this->valueExists('payerEmail')) {
            $order->setCustomerEmail($this->responseAddressInfo['payerEmail']);
        };
    }


}
