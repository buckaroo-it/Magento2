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
