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

/**
 * Resolves the Buckaroo "Culture" code for a transaction.
 *
 * The billing country is the authoritative signal: it selects the set of culture
 * codes Buckaroo/Klarna accept for that country. Within a country that has more
 * than one supported language (e.g. Belgium, Switzerland) an optional locale hint
 * (the store view locale the order was placed on) picks the matching language.
 * When the hint does not match, the country's primary culture is used.
 */
class CultureCodeResolver
{
    /**
     * Culture used when the billing country and locale hint provide no usable match.
     *
     * Matches the Buckaroo SDK's own default and is accepted by Klarna everywhere.
     */
    public const DEFAULT_CULTURE = 'en-GB';

    /**
     * Supported culture codes per billing country (ISO 3166-1 alpha-2).
     *
     * The first entry of each list is the country's primary/default culture.
     *
     * @var array<string, string[]>
     */
    public const COUNTRY_CULTURES = [
        'NL' => ['nl-NL', 'en-NL'],
        'BE' => ['nl-BE', 'fr-BE', 'be-BE', 'en-BE'],
        'DE' => ['de-DE', 'en-DE'],
        'AT' => ['de-AT', 'en-AT'],
        'CH' => ['de-CH', 'fr-CH', 'it-CH', 'en-CH'],
        'SE' => ['sv-SE', 'en-SE'],
        'NO' => ['nb-NO', 'en-NO'],
        'DK' => ['da-DK', 'en-DK'],
        'FI' => ['fi-FI', 'sv-FI', 'en-FI'],
        'GB' => ['en-GB'],
        'IE' => ['en-IE'],
        'FR' => ['fr-FR', 'en-FR'],
        'IT' => ['it-IT', 'en-IT'],
        'ES' => ['es-ES', 'en-ES'],
        'PT' => ['pt-PT', 'en-PT'],
        'PL' => ['pl-PL', 'en-PL'],
        'US' => ['en-US'],
    ];

    /**
     * Resolve the culture code for a billing country and optional locale hint.
     *
     * @param string|null $countryId  Billing address country id (ISO 3166-1 alpha-2)
     * @param string|null $localeHint Locale to disambiguate multi-language countries (e.g. "fr_BE", "nl-NL")
     *
     * @return string A culture code from the supported whitelist
     */
    public function resolve(?string $countryId, ?string $localeHint = null): string
    {
        $country = strtoupper(trim((string)$countryId));
        $language = $this->extractLanguage($localeHint);

        if (isset(self::COUNTRY_CULTURES[$country])) {
            $cultures = self::COUNTRY_CULTURES[$country];

            if ($language !== null) {
                foreach ($cultures as $culture) {
                    if (strpos($culture, $language . '-') === 0) {
                        return $culture;
                    }
                }
            }

            return $cultures[0];
        }

        // Unmapped country: honour the locale hint verbatim when it is a supported culture.
        $normalized = $this->normalizeLocale($localeHint);
        if ($normalized !== null && in_array($normalized, $this->getSupportedCultures(), true)) {
            return $normalized;
        }

        return self::DEFAULT_CULTURE;
    }

    /**
     * Flattened list of every supported culture code.
     *
     * @return string[]
     */
    public function getSupportedCultures(): array
    {
        return array_values(array_unique(array_merge([], ...array_values(self::COUNTRY_CULTURES))));
    }

    /**
     * Extract the lowercase 2-letter language part from a locale string.
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
        $language = strtolower($parts[0] ?? '');

        return preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : null;
    }

    /**
     * Normalize a locale hint to the "xx-YY" culture format, or null when it is not a full locale.
     *
     * @param string|null $localeHint
     *
     * @return string|null
     */
    private function normalizeLocale(?string $localeHint): ?string
    {
        if ($localeHint === null || trim($localeHint) === '') {
            return null;
        }

        $parts = preg_split('/[_-]/', trim($localeHint));
        if (count($parts) < 2) {
            return null;
        }

        $language = strtolower($parts[0]);
        $region = strtoupper($parts[1]);

        if (preg_match('/^[a-z]{2}$/', $language) !== 1 || preg_match('/^[A-Z]{2}$/', $region) !== 1) {
            return null;
        }

        return $language . '-' . $region;
    }
}
