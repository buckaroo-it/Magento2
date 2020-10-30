<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Gateway\Http\TransactionBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\Encryptor;
use Buckaroo\Magento2\Gateway\Http\Transaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

class Order extends AbstractTransactionBuilder
{
    private $emptyDescriptionFlag = false;

    /**
     * @return array
     */
    public function getBody()
    {
        if (!$this->getCurrency()) {
            $this->setOrderCurrency();
        }

        if ($this->getAmount() < 0.01) {
            $this->setOrderAmount();
        }

        $creditAmount = 0;
        if ($this->getType() == 'void') {
            $creditAmount = $this->getAmount();
            $this->setAmount(0);
        }

        $order = $this->getOrder();
        $store = $order->getStore();

        if ($this->configProviderAccount->getCreateOrderBeforeTransaction($store)) {
            $newStatus = $this->configProviderAccount->getOrderStatusNew($store);
            $orderState = 'new';
            if (!$newStatus) {
                $newStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }

            $order->setState($orderState);
            $order->setStatus($newStatus);
            $order->save();
        }

        $ip = $this->getIp($order);

        $billingData = $order->getBillingAddress();
        $shippingData = $order->getShippingAddress();
        $customParametersKey = $this->configProviderAccount->getCustomerAdditionalInfo($store);

        $body = [
            'Currency' => $this->getCurrency(),
            'AmountDebit' => $this->getAmount(),
            'AmountCredit' => $creditAmount,
            'Invoice' => $this->getInvoiceId(),
            'Order' => $order->getIncrementId(),
            'ClientIP' => (object)[
                '_' => $ip,
                'Type' => strpos($ip, ':') === false ? 'IPv4' : 'IPv6',
            ],
            'ReturnURL' => $this->getReturnUrl(),
            'ReturnURLCancel' => $this->getReturnUrl(),
            'ReturnURLError' => $this->getReturnUrl(),
            'ReturnURLReject' => $this->getReturnUrl(),
            'OriginalTransactionKey' => $this->originalTransactionKey,
            'StartRecurrent' => $this->startRecurrent,
            'PushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'Services' => (object)[
                'Service' => $this->getServices()
            ],
            'AdditionalParameters' => (object)[
                'AdditionalParameter' => $this->getAdditionalParameters(),
            ]
        ];

        if (!empty($customParametersKey)) {
            $body['CustomParameters']['CustomParameter'] = $this->getCustomInfo($customParametersKey, $billingData, $shippingData);

        }
//        $customData = 'CustomParameters' => (object)[
//        'CustomParameter' => $this->getCustomInfo($customParametersKey, $billingData, $shippingData)
//        ];

        if (!$this->emptyDescriptionFlag) {
            $body['Description'] = $this->configProviderAccount->getTransactionLabel($store);
        }

        $body = $this->filterBody($body);

        $customVars = $this->getCustomVars();
        if (is_array($customVars) && count($customVars) > 0) {
            foreach ($customVars as $key => $val) {
                $body[$key] = $val;
            }
        }

        return $body;
    }

    /**
     * @return array
     */
    private function getAdditionalParameters()
    {
        $parameterLine = [];
        if (isset($this->getServices()['Action'])) {
            $parameterLine[] = $this->getParameterLine('service_action_from_magento', strtolower($this->getServices()['Action']));
        }

        $parameterLine[] = $this->getParameterLine('initiated_by_magento', 1);

        if($additionalParameters = $this->getAllAdditionalParameters()){
            foreach ($additionalParameters as $key => $value) {
                $parameterLine[] = $this->getParameterLine($key, $value);
            }
        }

        return $parameterLine;
    }

    private function getCustomInfo($customParametersKey, $billingData, $shippingData)
    {
        $customParameters = $this->getCustomNeededFieldsList($customParametersKey);
        $customDataList = [];
        $customParamList = [];

        $customerBillingArray = $this->formatCustomData($customParameters, 'billing');
        $customerShippingArray = $this->formatCustomData($customParameters, 'shipping');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        foreach ($customerBillingArray as $key => $value) {
            if ($value != 'housenumber' && $value != 'houseadditionalnumber') {
                $customerBillingArray[$key] = '';
            }
            if (!empty($billingData->getData($value))){
                $customerBillingArray[$key] = $billingData->getData($value);
            }
            if ($value == 'country'){
                $customerBillingArray[$key] = $this->getCountryName($objectManager, $billingData);
            }
        }
        $this->setStreetData('CustomerBillingStreet',$customerBillingArray);
        $customerBillingArray = $this->getNotEmptyCustomData($customerBillingArray);

        foreach ($customerShippingArray as $key => $value) {
            if ($value != 'housenumber' && $value != 'houseadditionalnumber') {
                $customerShippingArray[$key] = '';
            }
            if (!empty($shippingData->getData($value))){
                $customerShippingArray[$key] = $shippingData->getData($value);
            }
            if ($value == 'country'){
                $customerShippingArray[$key] = $this->getCountryName($objectManager, $shippingData);
            }
        }
        $this->setStreetData('CustomerShippingStreet',$customerShippingArray);
        $customerShippingArray = $this->getNotEmptyCustomData($customerShippingArray);

        $customDataList = array_merge($customerBillingArray, $customerShippingArray);
        foreach ($customDataList as $key => $value) {
            $customParamList[] = $this->getParameterLine($key, $value);
        }

        return $customParamList;
    }

    private function setStreetData($addressData, &$customerData)
    {
        $streetFormat = [];
        if (!empty($customerData[$addressData])) {
            $street = preg_replace('[\s]', ' ', $customerData[$addressData]);
            $streetFormat = $this->formatStreet($street);
            $customerData[$addressData] = 'street';

            foreach ($customerData as $customerDataKey => $value) {
                if ($value == 'street') {
                    $customerData[$customerDataKey] = $streetFormat['street'];
                }
                if ($value == 'housenumber') {
                    $customerData[$customerDataKey] = $streetFormat['housenumber'];
                }
                if ($value == 'houseadditionalnumber') {
                    $customerData[$customerDataKey] = $streetFormat['numberaddition'];
                }
            }
        }
        foreach ($customerData as $customerDataKey => $value) {
            if ($value == 'housenumber' || $value == 'houseadditionalnumber') {
                $customerData[$customerDataKey] = '';
            }
        }
    }

    private function getCountryName($objectManager, $data)
    {
        $countryName = $objectManager->create('\Magento\Directory\Model\Country')->load($data->getData('country_id'))->getName();

        return $countryName;
    }

    private function getNotEmptyCustomData($customData)
    {
        foreach ($customData as $key => $value){
            if (empty($value)) {
                unset($customData[$key]);
            }
        }

        return $customData;
    }

    private function formatCustomData($customParameters, $address)
    {
        $customDataList = [];

        foreach ($customParameters[$address] as $customParameter) {
            $customParameterLabel = $this->getCustomParameterLabel($customParameter);
            $customValue = $this->getCustomParameterValue($customParameter);

            $customDataList[$customParameterLabel] = $customValue;
        }

        return $customDataList;
    }

    private function formatStreet($street)
    {
//        $street = implode(' ', $street);
//        $street = preg_split('[\s]', $street);

        $format = [
            'housenumber'    => '',
            'numberaddition' => '',
            'street'          => $street
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['housenumber'] = trim($matches[2]);
                $format['street']       = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['street']          = trim($matches[1]);
                    $format['housenumber']    = trim($matches[2]);
                    $format['numberaddition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }
    /**
     * @param $name
     * @param $value
     *
     * @return array
     */
    private function getParameterLine($name, $value)
    {
        $line = [
            '_'    => $value,
            'Name' => $name,
        ];

        return $line;
    }

    /**
     * @param array $body
     *
     * @return array
     */
    private function filterBody($body)
    {
        if ($this->getMethod() == 'CancelTransaction') {
            $body['Transaction'] = ['Key' => $body['OriginalTransactionKey']];
            unset($body['OriginalTransactionKey']);
        }

        $services = $this->getServices();

        if (!isset($services['Name']) || !isset($services['Action'])) {
            return $body;
        }

        if (($services['Name'] == 'paymentguarantee' && $services['Action'] == 'Order') ||
            ($services['Name'] == 'emandate' && $this->getMethod() == 'DataRequest')
        ) {
            unset($body['Invoice']);
        }

        if (($services['Name'] == 'paymentguarantee' && $services['Action'] == 'PartialInvoice') ||
            ($services['Name'] == 'klarnakp' && $services['Action'] == 'Pay')
        ) {
            unset($body['OriginalTransactionKey']);
        }

        if (($services['Name'] == 'capayable' && $services['Action'] == 'PayInInstallments')) {
            unset($body['Order']);
        }

        if ($services['Name'] == 'CreditManagement3' && $services['Action'] == 'CreateCreditNote') {
            unset($body['AmountCredit']);
            unset($body['OriginalTransactionKey']);
        }

        return $body;
    }

    /**
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    private function getAllowedCurrencies()
    {
        /**
         * @var \Buckaroo\Magento2\Model\Method\AbstractMethod $methodInstance
         */
        $methodInstance = $this->order->getPayment()->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode ?? 'buckaroo_magento2_ideal';

        $configProvider = $this->configProviderMethodFactory->get($method);
        return $configProvider->getAllowedCurrencies();
    }

    /**
     * @return $this
     */
    private function setOrderAmount()
    {
        if ($this->getCurrency() == $this->order->getOrderCurrencyCode()) {
            return $this->setAmount($this->order->getGrandTotal());
        }

        return $this->setAmount($this->order->getBaseGrandTotal());
    }

    /**
     * @return $this
     * @throws \Buckaroo\Magento2\Exception
     */
    private function setOrderCurrency()
    {
        if (in_array($this->order->getOrderCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getOrderCurrencyCode());
        }

        if (in_array($this->order->getBaseCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getBaseCurrencyCode());
        }

        throw new \Buckaroo\Magento2\Exception(
            __("The selected payment method does not support the selected currency or the store's base currency.")
        );
    }

    public function setEmptyDescriptionFlag($enabled)
    {
        $this->emptyDescriptionFlag = $enabled;
    }

    private function getCustomNeededFieldsList($customParameters)
    {
        $customParametersArray = explode(',', $customParameters);
        $customBillingData = [];
        $customShippingData = [];
        foreach ($customParametersArray as $customParameter) {
            if (strpos($customParameter, 'billing')) {
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

    public function getCustomParameterLabel($parameterKey)
    {
        $parameterLabel = str_replace(' ','', ucwords(str_replace('_', ' ', $parameterKey)));

        return $parameterLabel;
    }

    public function getCustomParameterValue($parameterKey)
    {
        $value = str_replace('_', '', preg_replace('/^customer_(billing|shipping)_/', '', $parameterKey));

        return $value;
    }
}
