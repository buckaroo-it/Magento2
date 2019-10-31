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
namespace TIG\Buckaroo\Service\Formatter;

use Magento\Sales\Api\Data\OrderAddressInterface;
use TIG\Buckaroo\Service\Formatter\Address\PhoneFormatter;
use TIG\Buckaroo\Service\Formatter\Address\StreetFormatter;

class AddressFormatter
{
    /** @var StreetFormatter */
    private $streetFormatter;

    /** @var PhoneFormatter */
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
     * @param OrderAddressInterface $address
     *
     * @return array
     */
    public function format($address)
    {
        $formattedAddress = [
            'street' => $this->formatStreet($address->getStreet()),
            'telephone' => $this->formatTelephone($address->getTelephone(), $address->getCountryId())
        ];

        return $formattedAddress;
    }

    /**
     * @param $street
     *
     * @return array
     */
    public function formatStreet($street)
    {
        return $this->streetFormatter->format($street);
    }

    /**
     * @param $phoneNumber
     * @param $country
     *
     * @return array
     */
    public function formatTelephone($phoneNumber, $country)
    {
        return $this->phoneFormatter->format($phoneNumber, $country);
    }
}
