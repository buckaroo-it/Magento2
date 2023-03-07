<?php

namespace Buckaroo\Magento2\Model;

class Applepay
{
    /**
     * Process Address From Wallet
     *
     * @param array $wallet
     * @param string $type
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => $wallet['givenName'] ?? 'Test',
            'middlename' => '',
            'lastname' => $wallet['familyName'] ?? 'Acceptatie',
            'street' => [
                '0' => $wallet['addressLines'][0] ?? 'Hoofdstraat',
                '1' => $wallet['addressLines'][1] ?? '80'
            ],
            'city' => $wallet['locality'] ?? 'Heerenveen',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '8441ER',
            'region' => $wallet['administrativeArea'] ?? 'Friesland',
            'region_id' => '',
            'postcode' => $wallet['postalCode'] ?? '8441ER',
            'telephone' => $wallet['phoneNumber'] ?? 'N/A',
            'fax' => '',
            'vat_id' => ''
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
