<?php
namespace Buckaroo\Magento2\Model;
class Applepay
{
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => 'Test firstname',
            'middlename' => '',
            'lastname' => 'Test lastname',
            'street' => [
                '0' => isset($wallet['addressLines'][0]) ? $wallet['addressLines'][0] : '',
                '1' => isset($wallet['addressLines'][1]) ? $wallet['addressLines'][1] : null
            ],
            'city' => isset($wallet['locality']) ? $wallet['locality'] : '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : 'NL',
            'region' => isset($wallet['administrativeArea']) ? $wallet['administrativeArea'] : 'unknown',
            'region_id' => 0,
            'postcode' => isset($wallet['postalCode']) ? $wallet['postalCode'] : '',
            'telephone' => isset($wallet['phoneNumber']) ? $wallet['phoneNumber'] : 'N/A',
            'fax' => '',
            'vat_id' => ''
        ];
        //this fails with array to string coversion critical error; as a result the address is not saved
        //made it one line
//        $address['street'] = implode("\n",$address['street']);
        $address['street'] = "Random street";
        if ($type == 'shipping') {
            $address['email'] = isset($wallet['emailAddress']) ? $wallet['emailAddress'] : '';
        }

        return $address;
    }
}
