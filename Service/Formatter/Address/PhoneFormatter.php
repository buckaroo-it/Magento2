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

namespace Buckaroo\Magento2\Service\Formatter\Address;

use Buckaroo\Magento2\Logging\Log;

class PhoneFormatter
{
    private Log $logger;

    private $validMobile = [
        'NL' => ['00316'],
        'BE' => ['003246', '003247', '003248', '003249'],
        'DE' => ['004915', '004916', '004917'],
        'AT' => ['0049650', '0049660', '0049664','0049676', '0049680', '0049677','0049681', '0049688', '0049699'],
    ];

    private $invalidNotation = [
        'NL' => ['00310', '0310', '310', '31'],
        'BE' => ['00320', '0320', '320', '32'],
    ];

    private $startingNotation = [
        'NL' => ['0', '0', '3', '1'],
        'BE' => ['0', '0', '3', '2'],
        'DE' => ['0', '0', '4', '9'],
        'AT' => ['0', '0', '4', '3'],
    ];

    public function __construct(
        Log $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param $phoneNumber
     * @param $country
     *
     * @return array
     */
    public function format($phoneNumber, $country)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
        $this->logger->addDebug(var_export([$phoneNumber, $country], true));

        $return = ["orginal" => $phoneNumber, "clean" => false, "mobile" => false, "valid" => false];

        $match = preg_replace('/[^0-9]/Uis', '', $phoneNumber);
        if ($match) {
            $phoneNumber = $match;
        }

        $return['clean'] = $this->formatPhoneNumber($phoneNumber, $country);
        $return['mobile'] = $this->isMobileNumber($return['clean'], $country);

        if (strlen((string)$return['clean']) == 13) {
            $return['valid'] = true;
        }

        $this->logger->addDebug(__METHOD__ . '|2|');
        $this->logger->addDebug(var_export($return, true));

        return $return;
    }

    /**
     * @param $phoneNumber
     * @param $country
     *
     * @return string
     */
    private function formatPhoneNumber($phoneNumber, $country)
    {
        $phoneLength = strlen((string)$phoneNumber);

        if ($phoneLength > 10 && $phoneLength != 13) {
            $phoneNumber = $this->isValidNotation($phoneNumber, $country);
        }

        if (
            (in_array($country, ['NL', 'BE']) && ($phoneLength == 10))
            ||
            (in_array($country, ['AT', 'DE']))
        ) {
            if (isset($this->startingNotation[$country])) {
                $notationStart = implode($this->startingNotation[$country]);
                $phoneNumber = $notationStart . substr($phoneNumber, 1);
            }
        }

        return $phoneNumber;
    }

    /**
     * @param $phoneNumber
     * @param $country
     *
     * @return bool
     */
    private function isMobileNumber($phoneNumber, $country)
    {
        $isMobile = false;

        if (isset($this->validMobile[$country])) {
            array_walk(
                $this->validMobile[$country],
                function ($value) use (&$isMobile, $phoneNumber) {
                    $phoneNumberPart = substr($phoneNumber, 0, strlen($value));
                    $phoneNumberHasValue = strpos($phoneNumberPart, $value);

                    if ($phoneNumberHasValue !== false) {
                        $isMobile = true;
                    }
                }
            );
        }

        return $isMobile;
    }

    /**
     * @param $phoneNumber
     * @param $country
     *
     * @return string
     */
    private function isValidNotation($phoneNumber, $country)
    {
        if (isset($this->invalidNotation[$country])) {
            array_walk(
                $this->invalidNotation[$country],
                function ($invalid) use (&$phoneNumber, $country) {
                    $phoneNumberPart = substr($phoneNumber, 0, strlen($invalid));

                    if (strpos($phoneNumberPart, $invalid) !== false) {
                        $phoneNumber = $this->formatNotation($phoneNumber, $invalid, $country);
                    }
                }
            );
        }

        return $phoneNumber;
    }

    /**
     * @param $phoneNumber
     * @param $invalid
     * @param $country
     *
     * @return string
     */
    private function formatNotation($phoneNumber, $invalid, $country)
    {
        if (isset($this->startingNotation[$country])) {
            $valid = substr($invalid, 0, -1);
            $countryNotation = $this->startingNotation[$country];

            if (substr($valid, 0, 2) == $countryNotation[2] . $countryNotation[3]) {
                $valid = $countryNotation[0] . $countryNotation[1] . $valid;
            }

            if (substr($valid, 0, 2) == $countryNotation[1] . $countryNotation[2]) {
                $valid = $countryNotation[0] . $valid;
            }

            if ($valid == $countryNotation[2]) {
                $valid = $countryNotation[1] . $valid . $countryNotation[3];
            }

            $phoneNumber = substr_replace($phoneNumber, $valid, 0, strlen($invalid));
        }

        return $phoneNumber;
    }
}
