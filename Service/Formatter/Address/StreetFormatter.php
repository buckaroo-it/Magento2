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

namespace Buckaroo\Magento2\Service\Formatter\Address;

class StreetFormatter
{
    /**
     * Formats the given street address array and returns a formatted array
     *
     * @param array $street
     * @return array
     */
    public function format(array $street): array
    {
        $street = $this->prepareStreetString($street);

        $format = [
            'house_number'    => '',
            'number_addition' => '',
            'street'          => $street
        ];

        $match = preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches);

        if ($match) {
            $format = $this->formatStreet($matches);
        }

        return $format;
    }

    /**
     * Street is always an array since it is parsed with two field objects.
     * Nondeless it could be that only the first field is parsed to the array
     *
     * @param array $street
     * @return string
     */
    private function prepareStreetString(array $street): string
    {
        $newStreet = $street[0];

        if (!empty($street[1])) {
            $newStreet .= ' ' . $street[1];
        }

        if (!empty($street[2])) {
            $newStreet .= ' ' . $street[2];
        }

        return $newStreet;
    }

    /**
     *  Formats the given street address by extracting the house number and number addition
     *
     * @param array $matches
     * @return array
     */
    private function formatStreet(array $matches): array
    {
        $format = [
            'house_number'    => trim($matches[2]),
            'number_addition' => '',
            'street'          => trim($matches[3]),
        ];

        if ('' != $matches[1]) {
            $format['street'] = trim($matches[1]);
            $format['number_addition'] = trim($matches[3]);
        }

        return $format;
    }
}
