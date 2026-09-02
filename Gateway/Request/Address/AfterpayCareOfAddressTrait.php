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

namespace Buckaroo\Magento2\Gateway\Request\Address;

trait AfterpayCareOfAddressTrait
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $address = $this->getAddress();
        $streetLines = $address->getStreet() ?? [];

        if ($this->isDachCountry($address->getCountryId())) {
            $streetForFormatting = isset($streetLines[0]) ? [$streetLines[0]] : [];
            $streetFormat = $this->formatStreet($streetForFormatting);
        } else {
            $streetFormat = [
                'street' => implode(' ', $streetLines),
                'house_number' => '',
                'number_addition' => '',
            ];
        }

        $addressData = [
            'street' => $streetFormat['street'],
            'houseNumber' => '',
            'houseNumberAdditional' => '',
            'zipcode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountryId()
        ];

        if (!empty($streetFormat['house_number'])) {
            $addressData['houseNumber'] = $streetFormat['house_number'];
        }

        if (!empty($streetFormat['number_addition'])) {
            $addressData['houseNumberAdditional'] = $streetFormat['number_addition'];
        }

        return ['address' => $addressData];
    }

    /**
     * Check whether the given country is a DACH country (DE, AT, CH).
     *
     * @param string $countryId
     *
     * @return bool
     */
    private function isDachCountry(string $countryId): bool
    {
        $dach_country_ids = ['DE', 'AT', 'CH'];

        return in_array($countryId, $dach_country_ids, true);
    }
}
