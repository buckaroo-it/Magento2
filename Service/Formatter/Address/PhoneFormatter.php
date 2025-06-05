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
    private const VALID_MOBILE = [
        'NL' => ['00316'],
        'BE' => ['003246', '003247', '003248', '003249'],
        'DE' => ['004915', '004916', '004917'],
        'AT' => ['0049650', '0049660', '0049664', '0049676', '0049680', '0049677', '0049681', '0049688', '0049699'],
    ];

    /**
     * @var array[]
     */
    private const INVALID_NOTATION = [
        'NL' => ['00310', '0310', '310', '31'],
        'BE' => ['00320', '0320', '320', '32'],
    ];

    /**
     * @var array[]
     */
    private const STARTING_NOTATION = [
        'NL' => '0031',
        'BE' => '0032',
        'DE' => '0049',
        'AT' => '0043',
    ];

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
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
        $this->logger->addDebug(__METHOD__ . ' - Starting format process', [
            'phoneNumber' => $phoneNumber,
            'country' => $country,
        ]);

        $return = [
            'original' => $phoneNumber,
            'clean' => false,
            'mobile' => false,
            'valid' => false,
        ];

        // If phone number is null or empty, return default
        if (empty($phoneNumber)) {
            return $return;
        }

        // If country is unsupported, return the original phone number as clean
        if (!isset(self::STARTING_NOTATION[$country])) {
            $return['clean'] = $phoneNumber;
            return $return;
        }

        // Clean and format the phone number
        $cleanedNumber = $this->cleanPhoneNumber($phoneNumber);
        $formattedNumber = $this->formatPhoneNumber($cleanedNumber, $country);

        // Check if the phone number is mobile and valid
        $return['clean'] = $formattedNumber;
        $return['mobile'] = $this->isMobileNumber($formattedNumber, $country);
        $return['valid'] = strlen($formattedNumber) === 13;

        $this->logger->addDebug(sprintf(
            '[PHONE_FORMATER] | [Service] | [%s:%s] - Format phone number by country | response: %s',
            __METHOD__,
            __LINE__,
            var_export($return, true)
        ));

        return $return;
    }

    /**
     * Removes all non-numeric characters from the phone number.
     *
     * @param string $phoneNumber
     * @return string
     */
    private function cleanPhoneNumber(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
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
        $startingNotation = self::STARTING_NOTATION[$country];
        $phoneLength = strlen($phoneNumber);

        // Apply invalid notation correction
        if ($phoneLength > 10 && $phoneLength !== 13) {
            $phoneNumber = $this->applyInvalidNotationCorrection($phoneNumber, $country);
        }

        // Prepend starting notation for supported local numbers
        if ($phoneLength === 10 && strpos($phoneNumber, $startingNotation) !== 0) {
            $phoneNumber = $startingNotation . substr($phoneNumber, 1);
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
    private function applyInvalidNotationCorrection(string $phoneNumber, string $country): string
    {
        foreach (self::INVALID_NOTATION[$country] ?? [] as $invalidPrefix) {
            if (strpos($phoneNumber, $invalidPrefix) === 0) {
                $phoneNumber = $this->replaceInvalidNotation($phoneNumber, $invalidPrefix, $country);
            }
        }
        return $phoneNumber;
    }

    /**
     * Replaces invalid notation with a valid prefix.
     *
     * @param string $phoneNumber
     * @param string $invalidPrefix
     * @param string $country
     * @return string
     */
    private function replaceInvalidNotation(string $phoneNumber, string $invalidPrefix, string $country): string
    {
        $validPrefix = self::STARTING_NOTATION[$country];
        return preg_replace('/^' . preg_quote($invalidPrefix, '/') . '/', $validPrefix, $phoneNumber) ?? $phoneNumber;
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
        foreach (self::VALID_MOBILE[$country] ?? [] as $prefix) {
            if (strpos($phoneNumber, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
}
