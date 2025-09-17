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

namespace Buckaroo\Magento2\Service\Formatter;

use Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter;
use Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AddressFormatter
{
    /**
     * @var StreetFormatter
     */
    private StreetFormatter $streetFormatter;

    /**
     * @var PhoneFormatter
     */
    private PhoneFormatter $phoneFormatter;

    /**
     * AddressFormatter constructor.
     *
     * @param StreetFormatter $streetFormatter
     * @param PhoneFormatter $phoneFormatter
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
     * @param string $country
     * @return array
     */
    public function formatTelephone(?string $phoneNumber, string $country): array
    {
        return $this->phoneFormatter->format($phoneNumber, $country);
    }
}
