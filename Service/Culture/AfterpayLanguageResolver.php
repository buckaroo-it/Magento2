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

namespace Buckaroo\Magento2\Service\Culture;

use Magento\Sales\Model\Order;

/**
 * Resolves the language code for the legacy Afterpay (DigiAccept) "Language" field.
 *
 * This is deliberately NOT a culture resolver. The SDK renames this builder's
 * "culture" key to "Language" (see AfterpayDigiAccept RecipientAdapter), and the
 * field takes a bare language code from a small supported set - not a culture code
 * such as "nl-NL", and certainly not a country code.
 *
 * The billing country selects the language; a locale hint disambiguates Belgium,
 * which is served in both Dutch and French.
 */
class AfterpayLanguageResolver
{
    /**
     * Language used when the billing country maps to nothing supported.
     *
     * Afterpay's primary market, and the value this field has effectively carried
     * for every Dutch order so far.
     */
    public const DEFAULT_LANGUAGE = 'NL';

    /**
     * Languages the Afterpay/Riverty conversation field accepts.
     *
     * @var string[]
     */
    public const SUPPORTED_LANGUAGES = ['NL', 'FR', 'DE', 'FI'];

    /**
     * Supported languages per billing country, most preferred first.
     *
     * @var array<string, string[]>
     */
    public const COUNTRY_LANGUAGES = [
        'NL' => ['NL'],
        'BE' => ['NL', 'FR'],
        'DE' => ['DE'],
        'AT' => ['DE'],
        'CH' => ['DE', 'FR'],
        'FR' => ['FR'],
        'FI' => ['FI'],
    ];

    /**
     * Resolve the language for an order's billing country.
     *
     * @param Order $order
     *
     * @return string One of {@see self::SUPPORTED_LANGUAGES}
     */
    public function resolveForOrder(Order $order): string
    {
        $billingAddress = $order->getBillingAddress();

        return $this->resolve(
            $billingAddress !== null ? $billingAddress->getCountryId() : null,
            $this->getStoreLocale($order)
        );
    }

    /**
     * Resolve the language for a billing country and optional locale hint.
     *
     * @param string|null $countryId  Billing address country id (ISO 3166-1 alpha-2)
     * @param string|null $localeHint Locale used to disambiguate Belgium (e.g. "fr_BE")
     *
     * @return string One of {@see self::SUPPORTED_LANGUAGES}
     */
    public function resolve(?string $countryId, ?string $localeHint = null): string
    {
        $country = strtoupper(trim((string)$countryId));

        if (!isset(self::COUNTRY_LANGUAGES[$country])) {
            return self::DEFAULT_LANGUAGE;
        }

        $languages = self::COUNTRY_LANGUAGES[$country];
        $hinted = $this->extractLanguage($localeHint);

        if ($hinted !== null && in_array($hinted, $languages, true)) {
            return $hinted;
        }

        return $languages[0];
    }

    /**
     * Get the locale of the store view the order was placed on.
     *
     * @param Order $order
     *
     * @return string|null
     */
    private function getStoreLocale(Order $order): ?string
    {
        try {
            $locale = (string)$order->getStore()->getConfig('general/locale/code');

            return $locale !== '' ? $locale : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Extract the upper-cased 2-letter language part from a locale string.
     *
     * @param string|null $localeHint
     *
     * @return string|null
     */
    private function extractLanguage(?string $localeHint): ?string
    {
        if ($localeHint === null || trim($localeHint) === '') {
            return null;
        }

        $parts = preg_split('/[_-]/', trim($localeHint));
        $language = strtoupper($parts[0] ?? '');

        return preg_match('/^[A-Z]{2}$/', $language) === 1 ? $language : null;
    }
}
