<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class AddressDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
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
     * Get address data
     *
     * @param string[]|null $street
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getAddressData(?array $street)
    {
        if (is_array($street)) {
            $street = implode(' ', $street);
        }

        $addressRegexResult = preg_match(
            '#\A(.*?)\s+(\d+[a-zA-Z]?\s?-\s?\d*[a-zA-Z]?|\d+[a-zA-Z-]?\d*[a-zA-Z]?)#',
            $street,
            $matches
        );

        if (!$addressRegexResult || !is_array($matches)) {
            return [
                'street'          => $street,
                'house_number'    => '',
                'number_addition' => '',
            ];
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

        return [
            'street'          => $streetname,
            'house_number'    => $housenumber,
            'number_addition' => $housenumberExtension,
        ];
    }
}
