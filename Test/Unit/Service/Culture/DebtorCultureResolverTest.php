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

namespace Buckaroo\Magento2\Test\Unit\Service\Culture;

use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers resolveForDebtor(), used by the Credit Management request builders.
 *
 * The expectations below were verified against the Buckaroo test gateway with
 * AddOrUpdateDebtor (BTI-1367): "za" and "na" return 491 Validation failure,
 * while "en-ZA", "en-NA", "en" and "nl-NL" all return 190 Success.
 */
class DebtorCultureResolverTest extends TestCase
{
    /**
     * @var CultureCodeResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CultureCodeResolver();
    }

    #[DataProvider('debtorCultureProvider')]
    public function testResolvesDebtorCulture(?string $country, ?string $hint, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolveForDebtor($country, $hint));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function debtorCultureProvider(): array
    {
        return [
            // The regression this ticket is about: a country code is never a culture.
            'south africa is not "za"'      => ['ZA', null, 'en-ZA'],
            'south africa ignores en_US'    => ['ZA', 'en_US', 'en-ZA'],
            'namibia is not "na"'           => ['NA', null, 'en-NA'],

            // Curated countries keep their primary language, hint or no hint.
            'netherlands stays dutch'       => ['NL', 'en_US', 'nl-NL'],
            'belgium defaults to dutch'     => ['BE', null, 'nl-BE'],
            'belgium honours french hint'   => ['BE', 'fr_BE', 'fr-BE'],
            'switzerland honours italian'   => ['CH', 'it_CH', 'it-CH'],

            // Countries added for Credit Management only.
            'brazil from debtor map'        => ['BR', null, 'pt-BR'],
            'japan from debtor map'         => ['JP', null, 'ja-JP'],
            'canada honours french hint'    => ['CA', 'fr_CA', 'fr-CA'],

            // Non-specific fallback, which the docs bless and the gateway accepts.
            'unknown country'               => ['XX', null, CultureCodeResolver::DEFAULT_DEBTOR_CULTURE],
            'empty country'                 => ['', null, CultureCodeResolver::DEFAULT_DEBTOR_CULTURE],
            'null country'                  => [null, null, CultureCodeResolver::DEFAULT_DEBTOR_CULTURE],
        ];
    }

    /**
     * ICU widens coverage to regions neither curated map names.
     *
     * Skipped when intl is unavailable, since the fallback is then correct.
     */
    public function testIcuWidensUnmappedRegions(): void
    {
        if (!class_exists(\ResourceBundle::class)) {
            $this->markTestSkipped('intl extension not available');
        }

        // Saudi Arabia is not in either curated map; ICU knows only Arabic for it.
        $this->assertSame('ar-SA', $this->resolver->resolveForDebtor('SA', null));
    }

    /**
     * A country code must never be emitted as a culture — that is the defect.
     */
    public function testNeverEmitsABareCountryCode(): void
    {
        foreach (['ZA', 'NA', 'NL', 'DE', 'FR', 'BE', 'BR', 'JP', 'XX'] as $country) {
            $culture = $this->resolver->resolveForDebtor($country, null);

            $this->assertNotSame(
                strtolower($country),
                $culture,
                sprintf('Country %s leaked into the culture field', $country)
            );
        }
    }

    /**
     * The debtor map must not leak into the Klarna path, which validates against
     * a closed locale enum that contains none of these countries.
     */
    public function testDebtorCountriesDoNotAffectTheKlarnaPath(): void
    {
        foreach (array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES) as $country) {
            $this->assertArrayNotHasKey(
                $country,
                CultureCodeResolver::COUNTRY_CULTURES,
                sprintf('%s must stay out of the Klarna-constrained map', $country)
            );

            $this->assertSame(
                CultureCodeResolver::DEFAULT_CULTURE,
                $this->resolver->resolve($country, null),
                sprintf('resolve() must not start returning a culture for %s', $country)
            );
        }
    }
}
