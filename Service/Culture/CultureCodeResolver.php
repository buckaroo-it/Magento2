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
     * Culture used when the billing country provides no usable match.
     *
     * Matches the Buckaroo SDK's own default and is a valid culture everywhere.
     */
    public const DEFAULT_CULTURE = 'en-GB';

    /**
     * Native culture codes per billing country (ISO 3166-1 alpha-2).
     *
     * The first entry of each list is the country's primary/default culture.
     * Every value is a real culture code accepted by the Buckaroo Culture header.
     *
     * @var array<string, string[]>
     */
    public const COUNTRY_CULTURES = [
        'NL' => ['nl-NL'],
        'BE' => ['nl-BE', 'fr-BE'],
        'DE' => ['de-DE'],
        'AT' => ['de-AT'],
        'CH' => ['de-CH', 'fr-CH', 'it-CH'],
        'SE' => ['sv-SE'],
        'NO' => ['nb-NO'],
        'DK' => ['da-DK'],
        'FI' => ['fi-FI', 'sv-FI'],
        'GB' => ['en-GB'],
        'IE' => ['en-IE'],
        'FR' => ['fr-FR'],
        'IT' => ['it-IT'],
        'ES' => ['es-ES'],
        'PT' => ['pt-PT'],
        'PL' => ['pl-PL'],
        'US' => ['en-US'],
    ];

    /**
     * Resolve the culture code for a billing country and optional locale hint.
     *
     * @param string|null $countryId  Billing address country id (ISO 3166-1 alpha-2)
     * @param string|null $localeHint Locale to disambiguate multi-language countries (e.g. "fr_BE", "nl-NL")
     *
     * @return string A real culture code accepted by the Buckaroo Culture header
     */
    public function resolve(?string $countryId, ?string $localeHint = null): string
    {
        $country = strtoupper(trim((string)$countryId));

        if (!isset(self::COUNTRY_CULTURES[$country])) {
            return self::DEFAULT_CULTURE;
        }

        $cultures = self::COUNTRY_CULTURES[$country];
        $language = $this->extractLanguage($localeHint);

        if ($language !== null) {
            foreach ($cultures as $culture) {
                if (strpos($culture, $language . '-') === 0) {
                    return $culture;
                }
            }
        }

        return $cultures[0];
    }

    /**
     * Flattened list of every culture code this resolver can emit.
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
}
