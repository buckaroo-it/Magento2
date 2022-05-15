<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\ApplePayGetShippingMethodsManagementInterface;
use Buckaroo\Magento2\Api\Data\WalletInterface;

class ApplePayGetShippingMethodsManagement implements ApplePayGetShippingMethodsManagementInterface
{

    /**
     * {@inheritdoc}
     */
    public function postApplePayGetShippingMethods(WalletInterface $wallet)
    {                
        return '{'.print_r($wallet,true).'}';
    }
}