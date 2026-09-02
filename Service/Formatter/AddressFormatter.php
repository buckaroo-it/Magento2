<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Formatter;

use Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter;
use Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AddressFormatter
{
    /**
     * @var StreetFormatter
     */
    private $streetFormatter;

    /**
     * @var PhoneFormatter
     */
    private $phoneFormatter;

    /**
     * AddressFormatter constructor.
     *
     * @param StreetFormatter $streetFormatter
     * @param PhoneFormatter  $phoneFormatter
     */
    public function __construct(
        StreetFormatter $streetFormatter,
        PhoneFormatter $phoneFormatter
    ) {
        $this->streetFormatter = $streetFormatter;
        $this->phoneFormatter = $phoneFormatter;
    }

    /**
     * Formats the address into a structured array.
     *
     * @param OrderAddressInterface $address
     *
     * @return array
     */
    public function format(OrderAddressInterface $address): array
    {
        return [
            'street' => $this->formatStreet($address->getStreet()),
            'telephone' => $this->formatTelephone($address->getTelephone(), $address->getCountryId()),
        ];
    }

    /**
     * Formats the street address.
     *
     * @param array|string|null $street
     *
     * @return array
     */
    public function formatStreet($street): array
    {
        return $this->streetFormatter->format($street);
    }

    /**
     * Formats the phone number based on the country.
     *
     * @param string|null $phoneNumber
     * @param string      $country
     *
     * @return array
     */
    public function formatTelephone(?string $phoneNumber, string $country): array
    {
        return $this->phoneFormatter->format($phoneNumber, $country);
    }
}
