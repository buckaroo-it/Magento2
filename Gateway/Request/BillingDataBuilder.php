<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Api\Data\OrderAddressInterface;

class BillingDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $this->order->getBillingAddress();
        $streetFormat = $this->formatStreet($billingAddress->getStreet());

        $birthDayStamp = str_replace('/', '-', (string)$this->payment->getAdditionalInformation('customer_DoB'));
        $identificationNumber = $this->payment->getAdditionalInformation('customer_identificationNumber');
        $telephone = $this->payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);
        $category = 'B2C';

        $gender = 'female';
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            $gender = 'male';
        }

        if ($this->getPayment()->getMethodInstance()->getCode() == 'buckaroo_magento2_klarnakp') {
            if (empty($billingAddress->getCompany())) {
                $careOf = 'Person';
            } else {
                $careOf = 'Company';
            }
            $billingData['recipient'] = [
                'careOf' => $careOf,
                'firstName' => $billingAddress->getFirstname(),
                'lastName' => $billingAddress->getLastName(),
            ];
        } else {
            $billingData['recipient'] = [
                'category' => $category,
                'gender' => $gender,
                'firstName' => $billingAddress->getFirstname(),
                'lastName' => $billingAddress->getLastName(),
                'birthDate' => $birthDayStamp
            ];
        }

        $billingData['address'] = [
            'street' => $streetFormat['street'],
            'houseNumber' => '',
            'houseNumberAdditional' => '',
            'zipcode' => $billingAddress->getPostcode(),
            'city' => $billingAddress->getCity(),
            'country' => $billingAddress->getCountryId()
        ];

        if (!empty($telephone)) {
            $billingData['phone'] = [
                'mobile' => $telephone,
                'landline' => $telephone
            ];
        }

        $billingData['email'] = $billingAddress->getEmail();

        if (!empty($streetFormat['house_number'])) {
            $billingData['address']['houseNumber'] = $streetFormat['house_number'];
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData['address']['houseNumberAdditional'] = $streetFormat['number_addition'];
        }

        if ($billingAddress->getCountryId() == 'FI') {
            $billingData['IdentificationNumber'] = $identificationNumber;
        }

        return ['billing' => $billingData];
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
}
