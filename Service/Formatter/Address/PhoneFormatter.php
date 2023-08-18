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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

class PhoneFormatter
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var array[]
     */
    private array $validMobile = [
        'NL' => ['00316'],
        'BE' => ['003246', '003247', '003248', '003249'],
        'DE' => ['004915', '004916', '004917'],
        'AT' => ['0049650', '0049660', '0049664', '0049676', '0049680', '0049677', '0049681', '0049688', '0049699'],
    ];

    /**
     * @var array[]
     */
    private array $invalidNotation = [
        'NL' => ['00310', '0310', '310', '31'],
        'BE' => ['00320', '0320', '320', '32'],
    ];

    /**
     * @var array[]
     */
    private array $startingNotation = [
        'NL' => ['0', '0', '3', '1'],
        'BE' => ['0', '0', '3', '2'],
        'DE' => ['0', '0', '4', '9'],
        'AT' => ['0', '0', '4', '3'],
    ];

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooLoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Format phone number by country
     *
     * @param string $phoneNumber
     * @param string $country
     * @return array
     */
    public function format(string $phoneNumber, string $country): array
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
     * Format phone number by country
     *
     * @param string $phoneNumber
     * @param string $country
     *
     * @return string
     */
    private function formatPhoneNumber(string $phoneNumber, string $country): string
    {
        $phoneLength = strlen((string)$phoneNumber);

        if ($phoneLength > 10 && $phoneLength != 13) {
            $phoneNumber = $this->isValidNotation($phoneNumber, $country);
        }

        if ((
                (in_array($country, ['NL', 'BE']) && ($phoneLength == 10))
                ||
                (in_array($country, ['AT', 'DE']))
            ) && (isset($this->startingNotation[$country]))) {
            $notationStart = implode($this->startingNotation[$country]);
            $phoneNumber = $notationStart . substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }

    /**
     * Checks if the phone number has a valid notation for the given country.
     *
     * @param string $phoneNumber
     * @param string $country
     * @return string
     */
    private function isValidNotation(string $phoneNumber, string $country): string
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
     * Formats the given phone number to have the correct notation for the country
     *
     * @param string $phoneNumber
     * @param string $invalid
     * @param string $country
     * @return string
     */
    private function formatNotation(string $phoneNumber, string $invalid, string $country): string
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

    /**
     * Check if is mobile number
     *
     * @param string $phoneNumber
     * @param string $country
     *
     * @return bool
     */
    private function isMobileNumber(string $phoneNumber, string $country): bool
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
}
