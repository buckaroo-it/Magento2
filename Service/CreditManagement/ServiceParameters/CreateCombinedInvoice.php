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
namespace TIG\Buckaroo\Service\CreditManagement\ServiceParameters;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Model\ConfigProvider\Method\AbstractConfigProvider;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;

class CreateCombinedInvoice
{
    /** @var AbstractConfigProvider */
    private $configProvider;

    /** @var Factory */
    private $configProviderMethodFactory;

    public function __construct(Factory $configProviderMethodFactory)
    {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param string                              $configProviderType
     *
     * @return array
     */
    public function get($payment, $configProviderType)
    {
        $this->configProvider = $this->configProviderMethodFactory->get($configProviderType);

        if (!$this->configProvider->getActiveStatusCm3()) {
            return [];
        }

        $services = [
            'Name'             => 'CreditManagement3',
            'Action'           => 'CreateCombinedInvoice',
            'Version'          => 1,
            'RequestParameter' => $this->getCmRequestParameters($payment)
        ];

        return $services;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getCmRequestParameters($payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        $requestParameters = [
            [
                '_'    => $order->getBillingAddress()->getEmail(),
                'Name' => 'Code',
                'Group' => 'Debtor',
            ],
            [
                '_'    => $order->getBillingAddress()->getEmail(),
                'Name' => 'Email',
                'Group' => 'Email',
            ],
            [
                '_'    => $order->getBillingAddress()->getTelephone(),
                'Name' => 'Mobile',
                'Group' => 'Phone',
            ],
        ];

        $ungroupedParameters = $this->getUngroupedCmParameters($order);
        $requestParameters = array_merge($requestParameters, $ungroupedParameters);

        $personParameters = $this->getPersonCmParameters($payment);
        $requestParameters = array_merge($requestParameters, $personParameters);

        $addressParameters = $this->getAddressCmParameters($order->getBillingAddress());
        $requestParameters = array_merge($requestParameters, $addressParameters);

        $companyParameters = $this->getCompanyCmParameters($order->getBillingAddress());
        $requestParameters = array_merge($requestParameters, $companyParameters);

        return $requestParameters;
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    private function getUngroupedCmParameters($order)
    {
        $ungroupedParameters = [
            [
                '_'    => $order->getGrandTotal(),
                'Name' => 'InvoiceAmount',
            ],
            [
                '_'    => $order->getTaxAmount(),
                'Name' => 'InvoiceAmountVAT',
            ],
            [
                '_'    => date('Y-m-d'),
                'Name' => 'InvoiceDate',
            ],
            [
                '_'    => date('Y-m-d', strtotime('+' . $this->configProvider->getCm3DueDate() . ' day', time())),
                'Name' => 'DueDate',
            ],
            [
                '_'    => $this->configProvider->getSchemeKey(),
                'Name' => 'SchemeKey',
            ],
            [
                '_'    => $this->configProvider->getMaxStepIndex(),
                'Name' => 'MaxStepIndex',
            ],
            [
                '_'    => $this->configProvider->getPaymentMethod(),
                'Name' => 'AllowedServices',
            ],
            [
                '_'    => $this->configProvider->getPaymentMethodAfterExpiry(),
                'Name' => 'AllowedServicesAfterDueDate',
            ],
        ];

        return $ungroupedParameters;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getPersonCmParameters($payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        $personParameters = [
            [
                '_'    => strtolower($order->getBillingAddress()->getCountryId()),
                'Name' => 'Culture',
                'Group' => 'Person',
            ],
            [
                '_'    => $order->getBillingAddress()->getFirstname(),
                'Name' => 'FirstName',
                'Group' => 'Person',
            ],
            [
                '_'    => $order->getBillingAddress()->getLastname(),
                'Name' => 'LastName',
                'Group' => 'Person',
            ],
            [
                '_'    => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'Gender',
                'Group' => 'Person',
            ],
        ];

        return $personParameters;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     *
     * @return array
     */
    private function getAddressCmParameters($billingAddress)
    {
        $address = $this->getCmAddress($billingAddress->getStreet());

        $addressParameters = [
            [
                '_'    => $address['street'],
                'Name' => 'Street',
                'Group' => 'Address',
            ],
            [
                '_'    => $address['house_number'],
                'Name' => 'HouseNumber',
                'Group' => 'Address',
            ],
            [
                '_'    => $billingAddress->getPostcode(),
                'Name' => 'Zipcode',
                'Group' => 'Address',
            ],
            [
                '_'    => $billingAddress->getCity(),
                'Name' => 'City',
                'Group' => 'Address',
            ],
            [
                '_'    => $billingAddress->getCountryId(),
                'Name' => 'Country',
                'Group' => 'Address',
            ],
        ];

        if (!empty($address['number_addition']) && strlen($address['number_addition']) > 0) {
            $addressParameters[] = [
                '_'    => $address['number_addition'],
                'Name' => 'HouseNumberSuffix',
                'Group' => 'Address'
            ];
        }

        return $addressParameters;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     *
     * @return array
     */
    private function getCompanyCmParameters($billingAddress)
    {
        $requestParameters = [];
        $company = $billingAddress->getCompany();

        if (strlen($company) <= 0) {
            return $requestParameters;
        }

        $requestParameters = [
            [
                '_' => strtolower($billingAddress->getCountryId()),
                'Name' => 'Culture',
                'Group' => 'Company'
            ],
            [
                '_' => $company,
                'Name' => 'Name',
                'Group' => 'Company'
            ]
        ];

        return $requestParameters;
    }

    /**
     * @param $street
     *
     * @return array
     */
    private function getCmAddress($street)
    {
        if (is_array($street)) {
            $street = implode(' ', $street);
        }

        $addressRegexResult = preg_match(
            '#\A(.*?)\s+(\d+[a-zA-Z]{0,1}\s{0,1}[-]{1}\s{0,1}\d*[a-zA-Z]{0,1}|\d+[a-zA-Z-]{0,1}\d*[a-zA-Z]{0,1})#',
            $street,
            $matches
        );
        if (!$addressRegexResult || !is_array($matches)) {
            $addressData = array(
                'street'           => $street,
                'house_number'          => '',
                'number_addition' => '',
            );

            return $addressData;
        }

        $streetname = '';
        $housenumber = '';
        $housenumberExtension = '';
        if (isset($matches[1])) {
            $streetname = $matches[1];
        }

        if (isset($matches[2])) {
            $housenumber = $matches[2];
        }

        if (!empty($housenumber)) {
            $housenumber = trim($housenumber);
            $housenumberRegexResult = preg_match('#^([\d]+)(.*)#s', $housenumber, $matches);
            if ($housenumberRegexResult && is_array($matches)) {
                if (isset($matches[1])) {
                    $housenumber = $matches[1];
                }

                if (isset($matches[2])) {
                    $housenumberExtension = trim($matches[2]);
                }
            }
        }

        $addressData = array(
            'street'          => $streetname,
            'house_number'    => $housenumber,
            'number_addition' => $housenumberExtension,
        );

        return $addressData;
    }
}
