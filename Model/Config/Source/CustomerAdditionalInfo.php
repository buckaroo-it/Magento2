<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace Buckaroo\Magento2\Model\Config\Source;


class CustomerAdditionalInfo implements \Magento\Framework\Option\ArrayInterface
{
    private $customFields = [
        'customer_billing_first_name'               => 'Customer Billing First Name',
        'customer_billing_last_name'                => 'Customer Billing Last Name',
        'customer_billing_company'                 => 'Customer Billing Company',
        'customer_billing_telephone'               => 'Customer Billing Phone',
        'customer_billing_email'                   => 'Customer Billing Email',
        'customer_billing_street'             => 'Customer Billing Street Name',
        'customer_billing_house_number'            => 'Customer Billing House Number',
        'customer_billing_house_additional_number' => 'Customer Billing House Additional Number',
        'customer_billing_city'                    => 'Customer Billing City',
        'customer_billing_region'                => 'Customer Billing Province',
        'customer_billing_postcode'                 => 'Customer Billing Zipcode',
        'customer_billing_country'                 => 'Customer Billing Country',
        'customer_shipping_first_name'               => 'Customer Shipping First Name',
        'customer_shipping_last_name'                => 'Customer Shipping Last Name',
        'customer_shipping_company'                 => 'Customer Shipping Company',
        'customer_shipping_telephone'               => 'Customer Shipping Phone',
        'customer_shipping_email'                   => 'Customer Shipping Email',
        'customer_shipping_street'             => 'Customer Shipping Street Name',
        'customer_shipping_house_number'            => 'Customer Shipping House Number',
        'customer_shipping_house_additional_number' => 'Customer Shipping House Additional Number',
        'customer_shipping_city'                    => 'Customer Shipping City',
        'customer_shipping_region'                => 'Customer Shipping Province',
        'customer_shipping_postcode'                 => 'Customer Shipping Zipcode',
        'customer_shipping_country'                 => 'Customer Shipping Country',

    ];

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->customFields as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        return $options;
    }
}
