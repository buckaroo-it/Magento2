<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class AddressDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        $billingAddress = $this->getOrder()->getBillingAddress();
        if ($billingAddress === null) {
            return [];
        }

        $addressData = $this->getAddressData(
            $billingAddress->getStreet()
        );

        return [
            'street'            => $addressData['street'],
            'houseNumber'       => $addressData['house_number'],
            'houseNumberSuffix' => $addressData['number_addition'],
            'zipcode'           => $billingAddress->getPostcode(),
            'city'              => $billingAddress->getCity(),
            'country'           => $billingAddress->getCountryId(),
        ];
    }

    /**
     * @param $street
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getAddressData($street)
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
                'street'           => $street,
                'house_number'          => '',
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
}
