<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AddressHandlerPool;
use Buckaroo\Magento2\Model\Method\Klarna\Klarnain;
use Buckaroo\Magento2\Plugin\Method\Klarna;

class ShippingDataBuilder extends AbstractDataBuilder
{
    private AddressHandlerPool $addressHandlerPool;

    public function __construct(
        AddressHandlerPool $addressHandlerPool
    )
    {
        $this->addressHandlerPool = $addressHandlerPool;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        // If the shipping address is not the same as the billing it will be merged inside the data array.
        if (
            $this->isAddressDataDifferent($this->getPayment()) ||
            is_null($this->getOrder()->getShippingAddress()) ||
            $this->getPayment()->getMethod() === Klarna::KLARNA_METHOD_NAME ||
            $this->getPayment()->getMethod() === Klarnain::PAYMENT_METHOD_CODE
        ) {
            $this->addressHandlerPool->updateShippingAddress($this->getOrder());
            return $this->getShippingData($this->getOrder()->getShippingAddress(), $this->getPayment());
        }

        return [];
    }

    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return boolean
     */
    public function isAddressDataDifferent($payment)
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

        return !empty($arrayDifferences);
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return array
     */
    private function calculateAddressDataDifference(array $addressOne, array $addressTwo): array
    {
        $keysToExclude = array_flip([
            'prefix',
            'telephone',
            'fax',
            'created_at',
            'email',
            'customer_address_id',
            'vat_request_success',
            'vat_request_date',
            'vat_request_id',
            'vat_is_valid',
            'vat_id',
            'address_type',
            'extension_attributes',
            'quote_address_id'
        ]);

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        return array_diff($filteredAddressOne, $filteredAddressTwo);
    }

    /**
     * @param $street
     *
     * @return array
     */
    public function formatStreet($street)
    {
        $street = implode(' ', $street);

        $format = [
            'house_number' => '',
            'number_addition' => '',
            'street' => $street,
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['street'] = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['street'] = trim($matches[1]);
                    $format['house_number'] = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }

    protected function getShippingData($shippingAddress, $payment): array
    {
        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $shippingAddress->getTelephone() : $telephone);
        $streetFormat = $this->formatStreet($shippingAddress->getStreet());
        $category = 'B2C';

        $gender = 'female';
        if ($payment->getAdditionalInformation('customer_gender') == '1') {
            $gender = 'male';
        }

        $shippingData['recipient'] = [
            'category' => $category,
            'gender' => $gender,
            'firstName' => $shippingAddress->getFirstname(),
            'lastName' => $shippingAddress->getLastName(),
            'birthDate' => $birthDayStamp ?? ''
        ];

        $shippingData['address'] = [
            'street' => $streetFormat['street'],
            'houseNumber' => '',
            'houseNumberAdditional' => '',
            'zipcode' => $shippingAddress->getPostcode(),
            'city' => $shippingAddress->getCity(),
            'country' => $shippingAddress->getCountryId()
        ];

        if (!empty($telephone)) {
            $shippingData['phone'] = [
                'mobile' => $telephone,
                'landline' => $telephone
            ];
        }

        $shippingData['email'] = $shippingAddress->getEmail();

        if (!empty($streetFormat['house_number'])) {
            $shippingData['address']['houseNumber'] = $streetFormat['house_number'];
        }

        if (!empty($streetFormat['number_addition'])) {
            $shippingData['address']['houseNumberAdditional'] = $streetFormat['number_addition'];
        }

        return ['shipping' => $shippingData];
    }
}
