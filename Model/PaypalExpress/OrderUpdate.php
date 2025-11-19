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
    private $buckarooResponseData;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooResponseDataInterface $buckarooResponseData
     * @param BuckarooLoggerInterface       $logger
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
     * Alternative method to get PayPal data from push notification
     * This handles the case where PayPal data comes through push notifications
     *
     * @return array|null
     */
    private function getAddressInfoFromPushData(): ?array
    {
        $fullResponse = $this->buckarooResponseData->getResponse()->toArray();

        // Extract PayPal data from push notification format
        $paypalData = [];
        if (is_array($fullResponse)) {
            foreach ($fullResponse as $key => $value) {
                // Look for brq_SERVICE_paypal_ prefixed fields
                if (strpos($key, 'brq_SERVICE_paypal_') === 0) {
                    // Remove the prefix to get the clean field name
                    $cleanKey = str_replace('brq_SERVICE_paypal_', '', $key);
                    $paypalData[$cleanKey] = $value;
                }
            }
        }

        return !empty($paypalData) ? $paypalData : null;
    }

    /**
     * Locate PayPal address info in the response, regardless of format
     *
     * @return array|null
     */
    private function getAddressInfoFromPayRequest(): ?array
    {
        $response = $this->buckarooResponseData->getResponse()->toArray();
        if (!$response) {
            return null;
        }

        $extractors = [
            fn() => $this->extractFromServiceParameters($response['Services'] ?? null),
            fn() => $this->extractFromLegacyApi($response),
            fn() => $this->getAddressInfoFromPushData(),
        ];

        foreach ($extractors as $extractor) {
            $data = $extractor();
            if (!empty($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * New API format – Services[n].Parameters
     *
     * @param ?array $services
     */
    private function extractFromServiceParameters(?array $services): ?array
    {
        if (!$services) {
            return null;
        }

        foreach ($services as $service) {
            if (($service['Name'] ?? '') === 'paypal' && isset($service['Parameters'])) {
                $data = $this->formatAddressData($service['Parameters']);
                if ($data) {
                    return $data;
                }
            }
        }
        return null;
    }

    /**
     * Legacy API format – Services.Service.ResponseParameter
     *
     * @param array $response
     */
    private function extractFromLegacyApi(array $response): ?array
    {
        $params = $response['Services']['Service']['ResponseParameter'] ?? null;
        if (!$params) {
            return null;
        }

        $data = $this->formatAddressData($params);
        return $data ?: null;
    }

    /**
     * Format address data in key/value pairs
     *
     * @param mixed $addressData
     *
     * @return array
     */
    public function formatAddressData($addressData): array
    {
        $data = [];
        if (!is_array($addressData)) {
            return $data;
        }

        foreach ($addressData as $addressItem) {
            // Handle new structure: {"Name": "fieldName", "Value": "fieldValue"}
            if (isset($addressItem['Name']) && isset($addressItem['Value'])) {
                $data[$addressItem['Name']] = $addressItem['Value'];
            } elseif (isset($addressItem->_) && isset($addressItem->Name)) {
                // Handle old structure: object with Name and _ properties
                $data[$addressItem->Name] = $addressItem->_;
            }
        }
        return $data;
    }

    /**
     * Update order address with pay response data
     *
     * @param mixed $address
     *
     * @return mixed
     */
    public function updateAddress($address)
    {
        // Skip update if address is already properly filled (not "unknown")
        if ($address->getFirstname() !== 'unknown' && $address->getFirstname() !== 'Guest') {
            return $address;
        }

        // Update basic info
        $this->updateItem($address, OrderAddressInterface::FIRSTNAME, 'payerFirstname');
        $this->updateItem($address, OrderAddressInterface::LASTNAME, 'payerLastname');
        $this->updateItem($address, OrderAddressInterface::EMAIL, 'payerEmail');

        // Update address fields with correct PayPal field mappings
        $this->updateStreetAddress($address);
        $this->updateItem($address, OrderAddressInterface::CITY, 'admin_area_2');
        $this->updateItem($address, OrderAddressInterface::POSTCODE, 'postal_code');
        $this->updateItem($address, OrderAddressInterface::COUNTRY_ID, 'payerCountry');

        // Phone is optional since PayPal doesn't always provide it
        $this->updateItemOptional($address, OrderAddressInterface::TELEPHONE, 'payerPhone');

        return $address;
    }

    /**
     * Update street address combining address_line_1 and address_line_2
     *
     * @param mixed $address
     */
    private function updateStreetAddress($address)
    {
        $street = [];

        // Get address line 1
        if ($this->valueExists('address_line_1')) {
            $street[] = $this->responseAddressInfo['address_line_1'];
        }

        // Get address line 2 (house number, etc.)
        if ($this->valueExists('address_line_2')) {
            $street[] = $this->responseAddressInfo['address_line_2'];
        }

        if (!empty($street)) {
            // Combine and sanitize street address
            $streetValue = implode(' ', $street);
            $streetValue = $this->sanitizeAddressField('street', $streetValue);
            $address->setData(OrderAddressInterface::STREET, [$streetValue]);
        }
    }

    /**
     * Update item but don't use default values if the field doesn't exist
     *
     * @param mixed  $address
     * @param string $addressField
     * @param string $responseField
     */
    private function updateItemOptional($address, $addressField, $responseField)
    {
        if ($this->valueExists($responseField)) {
            $value = $this->responseAddressInfo[$responseField];

            // Ensure we have a string value
            if (!is_string($value)) {
                $value = (string) $value;
            }

            // Only set if we have a meaningful value
            if (!empty(trim($value))) {
                $value = $this->sanitizeAddressField($addressField, $value);
                $address->setData($addressField, $value);
            }
        }
        // Don't set any default value - leave field empty
    }

    protected function updateItem($address, $addressField, $responseField)
    {
        if ($this->valueExists($responseField)) {
            $value = $this->responseAddressInfo[$responseField];

            // Ensure we have a string value
            if (!is_string($value)) {
                $value = (string) $value;
            }

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
     *
     * @return string
     */
    private function sanitizeAddressField(string $fieldType, string $value): string
    {
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

        return $value;
    }

    /**
     * Sanitize person name (firstname/lastname)
     *
     * @param string $name
     *
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
     *
     * @return string
     */
    private function sanitizePhoneNumber(string $phone): string
    {
        // Keep numbers, spaces, dashes, parentheses, and plus sign
        $sanitized = preg_replace('/[^\d\s\-\(\)\+]/', '', $phone);
        $sanitized = trim($sanitized);

        return $sanitized;
    }

    /**
     * Sanitize postcode
     *
     * @param string $postcode
     *
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

        // Final validation check
        if (!preg_match('/^[A-Za-z0-9\-\'\s]+$/', $sanitized)) {
            $sanitized = 'City';
        }

        return $sanitized;
    }

    private function valueExists($key): bool
    {
        return isset($this->responseAddressInfo[$key]) && is_string($this->responseAddressInfo[$key]);
    }

    /**
     * @param OrderInterface $order
     */
    public function updateEmail(OrderInterface $order)
    {
        if ($this->valueExists('payerEmail')) {
            $order->setCustomerEmail($this->responseAddressInfo['payerEmail']);
        };
    }
}
