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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Directory\Model\CountryFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class CustomParametersDataBuilder implements BuilderInterface
{
    /**
     * @var Account
     */
    protected Account $configProviderAccount;

    /**
     * @var CountryFactory
     */
    private CountryFactory $countryFactory;

    /**
     * Constructor
     *
     * @param Account $configProviderAccount
     * @param CountryFactory $countryFactory
     */
    public function __construct(
        Account $configProviderAccount,
        CountryFactory $countryFactory
    ) {
        $this->configProviderAccount = $configProviderAccount;
        $this->countryFactory = $countryFactory;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /** @var Order $order */
        $order = $paymentDO->getOrder()->getOrder();

        $store = $order->getStore();

        $customParametersKey = $this->configProviderAccount->getCustomerAdditionalInfo($store);

        if (!empty($customParametersKey)) {
            $billingData = $order->getBillingAddress();
            $shippingData = $order->getShippingAddress();
            $customParameters = $this->getCustomInfo(
                $customParametersKey,
                $billingData,
                $shippingData
            );
            return [
                'customParameters' => $customParameters
            ];
        }

        return [];
    }

    /**
     * Get custom parameters from billing and shipping address
     *
     * @param string $customParametersKey
     * @param OrderAddressInterface|null $billingData
     * @param OrderAddressInterface|null $shippingData
     * @return array
     */
    private function getCustomInfo(
        string $customParametersKey,
        ?OrderAddressInterface $billingData,
        ?OrderAddressInterface $shippingData
    ): array {
        $customParameters = $this->getCustomNeededFieldsList($customParametersKey);

        $customerBillingArray = $this->formatCustomData($customParameters, 'billing');
        $customerShippingArray = $this->formatCustomData($customParameters, 'shipping');

        $customerBillingArray = $this->getCustomerDataFromAddress($customerBillingArray, $billingData, 'Billing');

        if ($shippingData === null) {
            $shippingData = $billingData;
        }

        $customerShippingArray = $this->getCustomerDataFromAddress($customerShippingArray, $shippingData, 'Shipping');

        return array_merge($customerBillingArray, $customerShippingArray);
    }

    /**
     * @param $customerParameters
     * @param $addressData
     * @param $type
     * @return array
     */
    private function getCustomerDataFromAddress($customerParameters, $addressData, $type): array
    {
        foreach ($customerParameters as $key => $value) {
            if ($value != 'housenumber' && $value != 'houseadditionalnumber') {
                $customerParameters[$key] = '';
            }
            if (!empty($addressData->getData($value))) {
                $customerParameters[$key] = $addressData->getData($value);
            }
            if ($value == 'country') {
                $customerParameters[$key] = $this->getCountryName($addressData);
            }
        }
        $customerParameters = $this->setStreetData(
            'Customer' . $type . 'Street',
            $customerParameters
        );
        return $this->getNotEmptyCustomData($customerParameters);
    }

    /**
     * Get Custom Needed Fields
     *
     * @param string $customParameters
     * @return array
     */
    private function getCustomNeededFieldsList(string $customParameters): array
    {
        $customParametersArray = explode(',', $customParameters);
        $customBillingData = [];
        $customShippingData = [];
        foreach ($customParametersArray as $customParameter) {
            if (strpos($customParameter, 'billing') !== false) {
                $customBillingData[] = $customParameter;
            } else {
                $customShippingData[] = $customParameter;
            }
        }
        $customParametersArray = null;
        $customParametersArray['billing'] = $customBillingData;
        $customParametersArray['shipping'] = $customShippingData;

        return $customParametersArray;
    }

    /**
     * Format custom parameters
     *
     * @param $customParameters
     * @param $address
     * @return array
     */
    private function formatCustomData($customParameters, $address): array
    {
        $customDataList = [];

        foreach ($customParameters[$address] as $customParameter) {
            $customParameterLabel = $this->getCustomParameterLabel($customParameter);
            $customValue = $this->getCustomParameterValue($customParameter);

            $customDataList[$customParameterLabel] = $customValue;
        }

        return $customDataList;
    }

    /**
     * Format Parameter Label
     *
     * @param $parameterKey
     * @return array|string|string[]
     */
    public function getCustomParameterLabel($parameterKey)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $parameterKey)));
    }

    /**
     * Format Parameter Value
     *
     * @param $parameterKey
     * @return array|string|string[]|null
     */
    public function getCustomParameterValue($parameterKey)
    {
        return str_replace('_', '', preg_replace('/^customer_(billing|shipping)_/', '', $parameterKey));
    }

    /**
     * Get country name from country id
     *
     * @param $data
     * @return string
     */
    private function getCountryName($data): string
    {
        $countryName = '';
        $country = $this->countryFactory->create()->loadByCode($data->getData('country_id'));
        if ($country) {
            $countryName = $country->getName();
        }
        return $countryName;
    }

    /**
     * Set Street Data
     *
     * @param string $addressData
     * @param array $customerData
     * @return array
     */
    private function setStreetData(string $addressData, array $customerData)
    {
        if (!empty($customerData[$addressData])) {
            $street = preg_replace('[\s]', ' ', $customerData[$addressData]);
            $streetFormat = $this->formatStreet($street);
            $customerData[$addressData] = 'street';

            foreach ($customerData as $customerDataKey => $value) {
                if (in_array($value, ['street', 'housenumber', 'houseadditionalnumber']) ) {
                    $customerData[$customerDataKey] = $streetFormat[$value] ?? '';
                }
            }
        }

        foreach ($customerData as $customerDataKey => $value) {
            if ($value == 'housenumber' || $value == 'houseadditionalnumber') {
                $customerData[$customerDataKey] = '';
            }
        }

        return $customerData;
    }

    /**
     * Format street address
     *
     * @param $street
     * @return array
     */
    private function formatStreet($street): array
    {
        $format = [
            'housenumber' => '',
            'houseadditionalnumber' => '',
            'street' => $street
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['housenumber'] = trim($matches[2]);
                $format['street'] = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['street'] = trim($matches[1]);
                    $format['housenumber'] = trim($matches[2]);
                    $format['houseadditionalnumber'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }

    /**
     * Remove empty customer data
     *
     * @param array $customData
     * @return array
     */
    private function getNotEmptyCustomData(array $customData): array
    {
        foreach ($customData as $key => $value) {
            if (empty($value)) {
                unset($customData[$key]);
            }
        }

        return $customData;
    }
}
