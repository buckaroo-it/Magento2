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

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Model\Order\Address;

abstract class AbstractAddressDataBuilder extends AbstractDataBuilder
{
    /**
     * Builds address request
     *
     * @param array $buildSubject
     * @return array[]
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $address = $this->getAddress();

        $streetFormat = $this->formatStreet($address->getStreet());

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
     * Get Billing/Shipping address
     *
     * @return Address
     */
    abstract protected function getAddress(): Address;

    /**
     * Format street address
     *
     * @param string[] $street
     * @return array
     */
    public function formatStreet(array $street = null): array
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
