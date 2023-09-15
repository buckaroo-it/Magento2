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

namespace Buckaroo\Magento2\Service\CreditManagement\ServiceParameters;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class CreateCombinedInvoice
{
    /**
     * @var AbstractConfigProvider
     */
    private $configProvider;

    /**
     * @var Factory
     */
    private $configProviderMethodFactory;

    /**
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(Factory $configProviderMethodFactory)
    {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * Get request parameters for CM
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param string $configProviderType
     * @return array
     * @throws Exception
     */
    public function get($payment, string $configProviderType): array
    {
        $this->configProvider = $this->configProviderMethodFactory->get($configProviderType);

        if (!$this->configProvider->getActiveStatusCm3()) {
            return [];
        }

        return [
            'Name'             => 'CreditManagement3',
            'Action'           => 'CreateCombinedInvoice',
            'Version'          => 1,
            'RequestParameter' => $this->getCmRequestParameters($payment)
        ];
    }

    /**
     * Get debtor details
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @return array
     */
    private function getCmRequestParameters($payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        $requestParameters = [
            [
                '_'     => $order->getBillingAddress()->getEmail(),
                'Name'  => 'Code',
                'Group' => 'Debtor',
            ],
            [
                '_'     => $order->getBillingAddress()->getEmail(),
                'Name'  => 'Email',
                'Group' => 'Email',
            ],
        ];

        if ($order->getBillingAddress()->getTelephone()) {
            $requestParameters[] = [
                '_'     => $order->getBillingAddress()->getTelephone(),
                'Name'  => 'Mobile',
                'Group' => 'Phone',
            ];
        }

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
     * Get invoice data
     *
     * @param Order $order
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
                '_'    => $this->getAllowedServices($order->getPayment()),
                'Name' => 'AllowedServices',
            ]
        ];

        if ($this->configProvider->getPaymentMethodAfterExpiry()) {
            $ungroupedParameters[] = [
                '_'    => $this->configProvider->getPaymentMethodAfterExpiry(),
                'Name' => 'AllowedServicesAfterDueDate',
            ];
        }

        return $ungroupedParameters;
    }

    /**
     * Get allowed services
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @return string
     */
    private function getAllowedServices($payment): string
    {
        $allowedServices = $this->configProvider->getPaymentMethod();

        if (!is_string($allowedServices)) {
            return '';
        }

        $allowedServices = $this->appendGiftcards($allowedServices);

        if ($payment->getMethod() === PayPerEmail::CODE) {
            return str_replace("p24,", "", $allowedServices);
        }

        return $allowedServices;
    }

    /**
     * Append active giftcards if giftcard is enabled
     *
     * @param string $allowedServices
     *
     * @return string
     */
    private function appendGiftcards(string $allowedServices): string
    {
        $services = explode(',', $allowedServices);
        if (!in_array('giftcard', $services)) {
            return $allowedServices;
        }
        $services = array_filter($services, function ($service) {
            return $service !== 'giftcard';
        });

        $allowedServices = implode(",", $services);

        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards */
        $giftcardConfig = $this->configProviderMethodFactory->get('giftcards');

        if (!method_exists($giftcardConfig, 'getAllowedCards')) {
            return $allowedServices;
        }
        $activeGiftcardIssuers = $giftcardConfig->getAllowedCards();

        if (!is_string($activeGiftcardIssuers) || strlen(trim($activeGiftcardIssuers)) === 0) {
            return $allowedServices;
        }

        if (strlen($allowedServices) > 0) {
            return $allowedServices . "," . $activeGiftcardIssuers;
        }
        return $activeGiftcardIssuers;
    }

    /**
     * Get CM Person details
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @return array
     */
    private function getPersonCmParameters($payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        $personParameters = [
            [
                '_'     => strtolower($order->getBillingAddress()->getCountryId()),
                'Name'  => 'Culture',
                'Group' => 'Person',
            ],
            [
                '_'     => $order->getBillingAddress()->getFirstname(),
                'Name'  => 'FirstName',
                'Group' => 'Person',
            ],
            [
                '_'     => $order->getBillingAddress()->getLastname(),
                'Name'  => 'LastName',
                'Group' => 'Person',
            ],
        ];

        if (!empty($payment->getAdditionalInformation('customer_gender'))) {
            $personParameters[] = [
                '_'     => $payment->getAdditionalInformation('customer_gender'),
                'Name'  => 'Gender',
                'Group' => 'Person',
            ];
        }

        return $personParameters;
    }

    /**
     * Get Address CM Parameters
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     * @return array
     */
    private function getAddressCmParameters($billingAddress)
    {
        $address = $this->getCmAddress($billingAddress->getStreet());

        $addressParameters = [
            [
                '_'     => $address['street'],
                'Name'  => 'Street',
                'Group' => 'Address',
            ],
            [
                '_'     => $address['house_number'],
                'Name'  => 'HouseNumber',
                'Group' => 'Address',
            ],
            [
                '_'     => $billingAddress->getPostcode(),
                'Name'  => 'Zipcode',
                'Group' => 'Address',
            ],
            [
                '_'     => $billingAddress->getCity(),
                'Name'  => 'City',
                'Group' => 'Address',
            ],
            [
                '_'     => $billingAddress->getCountryId(),
                'Name'  => 'Country',
                'Group' => 'Address',
            ],
        ];

        if (!empty($address['number_addition']) && strlen($address['number_addition']) > 0) {
            $addressParameters[] = [
                '_'     => $address['number_addition'],
                'Name'  => 'HouseNumberSuffix',
                'Group' => 'Address'
            ];
        }

        return $addressParameters;
    }

    /**
     * Get CM Address
     *
     * @param $street
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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
            $addressData = [
                'street'          => $street,
                'house_number'    => '',
                'number_addition' => '',
            ];

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

        $addressData = [
            'street'          => $streetname,
            'house_number'    => $housenumber,
            'number_addition' => $housenumberExtension,
        ];

        return $addressData;
    }

    /**
     * Get Company CM Parameters
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     * @return array
     */
    private function getCompanyCmParameters($billingAddress)
    {
        $requestParameters = [];
        $company = $billingAddress->getCompany();

        if (empty($company) || strlen($company) <= 0) {
            return $requestParameters;
        }

        $requestParameters = [
            [
                '_'     => strtolower($billingAddress->getCountryId()),
                'Name'  => 'Culture',
                'Group' => 'Company'
            ],
            [
                '_'     => $company,
                'Name'  => 'Name',
                'Group' => 'Company'
            ]
        ];

        return $requestParameters;
    }
}
