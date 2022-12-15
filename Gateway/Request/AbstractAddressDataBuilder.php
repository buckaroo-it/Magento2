<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Address;

abstract class AbstractAddressDataBuilder extends AbstractDataBuilder
{
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
     * @param $street
     *
     * @return array
     */
    public function formatStreet($street): array
    {
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

    /**
     * @return Address
     */
    abstract protected function getAddress(): Address;
}
