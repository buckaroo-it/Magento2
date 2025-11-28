<?php

namespace Buckaroo\Magento2\Model;

class Applepay
{
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => $wallet['givenName'] ?? '',
            'middlename' => '',
            'lastname' => $wallet['familyName'] ?? '',
            'street' => [
                '0' => $wallet['addressLines'][0] ?? '',
                '1' => $wallet['addressLines'][1] ?? null,
            ],
            'city' => $wallet['locality'] ?? '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region' => $wallet['administrativeArea'] ?? '',
            'region_id' => '',
            'postcode' => $wallet['postalCode'] ?? '',
            'telephone' => $wallet['phoneNumber'] ?? 'N/A',
            'fax' => '',
            'vat_id' => '',
        ];
        //this fails with array to string coversion critical error; as a result the address is not saved
        //made it one line
        $address['street'] = implode("\n", $address['street']);
        if ($type == 'shipping') {
            $address['email'] = $wallet['emailAddress'] ?? '';
        }

        return $address;
    }
}
