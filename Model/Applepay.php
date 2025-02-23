<?php
namespace Buckaroo\Magento2\Model;
class Applepay
{
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => isset($wallet['givenName']) ? $wallet['givenName'] : '',
            'middlename' => '',
            'lastname' => isset($wallet['familyName']) ? $wallet['familyName'] : '',
            'street' => [
                '0' => isset($wallet['addressLines'][0]) ? $wallet['addressLines'][0] : '',
                '1' => isset($wallet['addressLines'][1]) ? $wallet['addressLines'][1] : null
            ],
            'city' => isset($wallet['locality']) ? $wallet['locality'] : '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region' => isset($wallet['administrativeArea']) ? $wallet['administrativeArea'] : '',
            'region_id' => '',
            'postcode' => isset($wallet['postalCode']) ? $wallet['postalCode'] : '',
            'telephone' => isset($wallet['phoneNumber']) ? $wallet['phoneNumber'] : 'N/A',
            'fax' => '',
            'vat_id' => ''
        ];
        //this fails with array to string coversion critical error; as a result the address is not saved
        //made it one line
        $address['street'] = implode("\n",$address['street']);
        if ($type == 'shipping') {
            $address['email'] = isset($wallet['emailAddress']) ? $wallet['emailAddress'] : '';
        }

        return $address;
    }
}
