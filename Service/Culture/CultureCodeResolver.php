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

namespace Buckaroo\Magento2\Service\Culture;

use Magento\Sales\Model\Order;

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
     * Culture used for a debtor when the billing country provides no usable match.
     *
     * Credit Management documents a "non-specific" culture (a bare language code)
     * as valid, unlike the closed locale enum the Klarna services validate against.
     */
    public const DEFAULT_DEBTOR_CULTURE = 'en';

    /**
     * Extra billing countries recognised for Credit Management only.
     *
     * {@see self::COUNTRY_CULTURES} mirrors the closed locale enum the Klarna
     * services accept, so it must not grow. Credit Management has no such enum,
     * so these countries — carried over from the 1.x CultureCodeMapper — are kept
     * separate to widen debtor coverage without loosening the Klarna path.
     *
     * @var array<string, string[]>
     */
    public const DEBTOR_COUNTRY_CULTURES = [
        'US' => ['en-US'],
        'ZA' => ['en-ZA'],
        'NA' => ['en-NA'],
        'CA' => ['en-CA', 'fr-CA'],
        'AU' => ['en-AU'],
        'NZ' => ['en-NZ'],
        'IN' => ['en-IN'],
        'LI' => ['de-LI'],
        'LU' => ['de-LU', 'fr-LU'],
        'MX' => ['es-MX'],
        'AR' => ['es-AR'],
        'CL' => ['es-CL'],
        'CO' => ['es-CO'],
        'PE' => ['es-PE'],
        'BR' => ['pt-BR'],
        'CZ' => ['cs-CZ'],
        'SK' => ['sk-SK'],
        'HU' => ['hu-HU'],
        'RO' => ['ro-RO'],
        'BG' => ['bg-BG'],
        'GR' => ['el-GR'],
        'TR' => ['tr-TR'],
        'RU' => ['ru-RU'],
        'JP' => ['ja-JP'],
        'CN' => ['zh-CN'],
        'TW' => ['zh-TW'],
        'KR' => ['ko-KR'],
        'CD' => ['fr-CD'],
        'CG' => ['fr-CG'],
        'CI' => ['fr-CI'],
        'SN' => ['fr-SN'],
        'CM' => ['fr-CM'],
    ];

    /**
     * Languages ICU knows per region, lazily built once. Keyed by region.
     *
     * @var array<string, array<string, true>>|null
     */
    private static $icuLanguagesByRegion = null;

    /**
     * Native culture codes per billing country (ISO 3166-1 alpha-2).
     *
     * The first entry of each list is the country's primary/default culture.
     * Every value appears in the locale enum the Klarna services validate against,
     * so nothing may be added here without checking that list first (en-US, for one,
     * is absent from it and therefore lives in DEBTOR_COUNTRY_CULTURES instead).
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

        return $this->pickCulture(
            self::COUNTRY_CULTURES[$country],
            $this->extractLanguage($localeHint)
        );
    }

    /**
     * Resolve the Credit Management debtor culture for an order.
     *
     * Single entry point for the Credit Management request builders, so the
     * billing country and store locale are read the same way at every call site.
     *
     * @param Order $order
     *
     * @return string A culture code accepted by Credit Management
     */
    public function resolveDebtorCultureForOrder(Order $order): string
    {
        $billingAddress = $order->getBillingAddress();

        return $this->resolveForDebtor(
            $billingAddress !== null ? $billingAddress->getCountryId() : null,
            $this->getStoreLocale($order)
        );
    }

    /**
     * Get the locale of the store view the order was placed on.
     *
     * Used only to disambiguate multi-language countries.
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
     * Resolve the culture code for a Credit Management debtor.
     *
     * Unlike {@see self::resolve()}, which is bound by the closed locale enum the
     * Klarna services accept, Credit Management accepts any specific culture and
     * also a non-specific (bare language) code. Coverage is therefore widened in
     * three steps: the curated maps first, then ICU for regions those maps do not
     * name, and finally a non-specific fallback.
     *
     * @param string|null $countryId  Billing address country id (ISO 3166-1 alpha-2)
     * @param string|null $localeHint Locale to disambiguate multi-language countries (e.g. "fr_BE")
     *
     * @return string A culture code accepted by Credit Management
     */
    public function resolveForDebtor(?string $countryId, ?string $localeHint = null): string
    {
        $country = strtoupper(trim((string)$countryId));

        if ($country === '') {
            return self::DEFAULT_DEBTOR_CULTURE;
        }

        $language = $this->extractLanguage($localeHint);

        $curated = self::COUNTRY_CULTURES[$country] ?? self::DEBTOR_COUNTRY_CULTURES[$country] ?? null;
        if ($curated !== null) {
            return $this->pickCulture($curated, $language);
        }

        return $this->resolveFromIcu($country, $language) ?? self::DEFAULT_DEBTOR_CULTURE;
    }

    /**
     * Resolve the culture code for the Buckaroo "Culture" HTTP header.
     *
     * The header is validated globally, before any method logic: an unknown value
     * fails the whole request with a 400, so this must never emit a code it has not
     * been shown to accept. Gateway probing (BTI-1378) shows the validator accepts
     * any real language or culture code and rejects only unknown ones, which makes
     * both curated maps safe here — unlike {@see self::resolve()}, which is narrowed
     * to the closed locale enum the Klarna body parameters validate against.
     *
     * ICU-derived cultures are deliberately excluded: they are real combinations but
     * unverified against this validator, and the cost of guessing wrong is a failed
     * payment rather than a mistranslated page.
     *
     * Returns null when the billing country names no curated culture, so the caller
     * can leave the header alone rather than replace a working value with a guess.
     *
     * @param string|null $countryId  Billing address country id (ISO 3166-1 alpha-2)
     * @param string|null $localeHint Locale to disambiguate multi-language countries (e.g. "fr_BE")
     *
     * @return string|null Culture code, or null when the country is not curated
     */
    public function resolveForHeader(?string $countryId, ?string $localeHint = null): ?string
    {
        $country = strtoupper(trim((string)$countryId));

        $curated = self::COUNTRY_CULTURES[$country] ?? self::DEBTOR_COUNTRY_CULTURES[$country] ?? null;

        if ($curated === null) {
            return null;
        }

        return $this->pickCulture($curated, $this->extractLanguage($localeHint));
    }

    /**
     * Resolve a culture for a region the curated maps do not name, using ICU data.
     *
     * ICU lists every language it knows per region but gives no way to rank them —
     * PHP does not expose the likely-subtags API, and the list order is alphabetical.
     * So a language is only chosen when the choice is unambiguous: either the store
     * locale names a language ICU considers valid for the region, or the region has
     * exactly one language.
     *
     * @param string      $country  Billing country, already upper-cased
     * @param string|null $language Language from the locale hint, already normalised
     *
     * @return string|null Culture code, or null when ICU cannot decide
     */
    private function resolveFromIcu(string $country, ?string $language): ?string
    {
        $languages = $this->getIcuLanguages($country);

        if ($languages === []) {
            return null;
        }

        if ($language !== null && isset($languages[$language])) {
            return $language . '-' . $country;
        }

        if (count($languages) === 1) {
            return array_key_first($languages) . '-' . $country;
        }

        return null;
    }

    /**
     * Languages ICU associates with a region.
     *
     * @param string $country Billing country, already upper-cased
     *
     * @return array<string, true>
     */
    private function getIcuLanguages(string $country): array
    {
        if (self::$icuLanguagesByRegion === null) {
            self::$icuLanguagesByRegion = $this->buildIcuLanguageIndex();
        }

        return self::$icuLanguagesByRegion[$country] ?? [];
    }

    /**
     * Build the region to language index once from ICU's locale list.
     *
     * @return array<string, array<string, true>>
     */
    private function buildIcuLanguageIndex(): array
    {
        if (!class_exists(\ResourceBundle::class) || !class_exists(\Locale::class)) {
            return [];
        }

        try {
            $locales = \ResourceBundle::getLocales('');
        } catch (\Throwable $exception) {
            return [];
        }

        if (!is_array($locales)) {
            return [];
        }

        $index = [];
        foreach ($locales as $locale) {
            $parts = \Locale::parseLocale($locale);
            $region = $parts['region'] ?? null;
            $language = $parts['language'] ?? null;

            if ($region !== null && $language !== null) {
                $index[strtoupper($region)][strtolower($language)] = true;
            }
        }

        return $index;
    }

    /**
     * Pick the culture matching the language, falling back to the country's primary.
     *
     * @param string[]    $cultures
     * @param string|null $language
     *
     * @return string
     */
    private function pickCulture(array $cultures, ?string $language): string
    {
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
