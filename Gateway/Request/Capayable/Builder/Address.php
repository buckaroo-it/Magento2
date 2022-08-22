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

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Capayable\Builder;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

class Address extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $this->getOrder()->getBillingAddress();

        $streetData = $this->formatStreet($billingAddress->getStreet());
        $address = [
            'street'                => $streetData['street'],
            'houseNumber'           => $streetData['house_number'],
            'zipcode'               => $billingAddress->getPostcode(),
            'city'                  => $billingAddress->getCity(),
            'country'               => $billingAddress->getCountryId()
        ];

        if (strlen($streetData['number_addition']) > 0) {
            $address['houseNumberAdditional'] = $streetData['number_addition'];
        }

        return [
            'address'   => $address
        ];
    }
    /**
     * Get street fragments
     * 
     * @param array|null $street
     * @return array
     */
    protected function formatStreet(array $street = null)
    {
        if (!is_array($street)) {
            return [
                'house_number' => '',
                'number_addition' => '',
                'street' => '',
            ];
        }
        $street = implode(' ', $street);

        $format = [
            'house_number' => '',
            'number_addition' => '',
            'street' => $street,
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['street'] = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['street'] = trim($matches[1]);
                    $format['house_number'] = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }
}
