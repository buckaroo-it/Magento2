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

namespace Buckaroo\Magento2\Test\Unit\Service\Culture;

use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Buckaroo "Culture" HTTP header is validated globally: an unknown value fails
 * the request with a 400 before any method logic runs. These tests pin the two
 * guarantees that make it safe to send the header on every payment method.
 */
class HeaderCultureResolverTest extends TestCase
{
    /**
     * @var CultureCodeResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CultureCodeResolver();
    }

    #[DataProvider('headerCultureProvider')]
    public function testResolvesHeaderCulture(?string $country, ?string $hint, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolveForHeader($country, $hint));
    }

    public static function headerCultureProvider(): array
    {
        return [
            // The BTI-1378 QA finding: a Belgian order on an en_US store sent en-US.
            'BE on english store'   => ['BE', 'en_US', 'nl-BE'],
            'BE on french store'    => ['BE', 'fr_BE', 'fr-BE'],
            'BE without hint'       => ['BE', null, 'nl-BE'],
            'NL on english store'   => ['NL', 'en_US', 'nl-NL'],
            'CH on italian store'   => ['CH', 'it_CH', 'it-CH'],

            // Countries only the debtor map names — legal headers, proven by gateway probe.
            'US'                    => ['US', 'en_US', 'en-US'],
            'CA on french store'    => ['CA', 'fr_CA', 'fr-CA'],
            'ZA'                    => ['ZA', null, 'en-ZA'],

            // BTI-1578: the gateway rejects en-NA and fr-CG outright, so each degrades to
            // its own verified bare language rather than to en-GB or to no header at all.
            'NA degrades to en'     => ['NA', 'nl_NL', 'en'],
            'CG degrades to fr'     => ['CG', 'en_US', 'fr'],

            // Nothing curated -> no header override, so the adapter keeps today's value.
            'uncurated country'     => ['XK', 'en_US', null],
            'empty country'         => ['', null, null],
            'null country'          => [null, null, null],
        ];
    }

    /**
     * Widening the header must not change what the verified Klarna path already sends.
     */
    public function testHeaderMatchesTheStrictResolverForEveryKlarnaCountry(): void
    {
        foreach (array_keys(CultureCodeResolver::COUNTRY_CULTURES) as $country) {
            foreach ([null, 'nl_NL', 'fr_BE', 'de_DE', 'it_CH', 'en_US', 'sv_FI'] as $hint) {
                $this->assertSame(
                    $this->resolver->resolve($country, $hint),
                    $this->resolver->resolveForHeader($country, $hint),
                    sprintf('Header culture for %s / %s drifted from the strict resolver', $country, (string)$hint)
                );
            }
        }
    }

    /**
     * A 400 "not a known culture" fails the payment, so only curated values may be emitted.
     *
     * @param string $country
     * @param array  $expected One culture per hint: none, en_US, nl_NL, fr_FR, de_DE, it_IT
     */
    #[DataProvider('curatedCultureProvider')]
    public function testOnlyEverEmitsACuratedCulture(string $country, array $expected): void
    {
        $emitted = array_map(function ($hint) use ($country) {
            return $this->resolver->resolveForHeader($country, $hint);
        }, [null, 'en_US', 'nl_NL', 'fr_FR', 'de_DE', 'it_IT']);

        $this->assertSame($expected, $emitted);
    }

    public static function curatedCultureProvider(): array
    {
        return [
            'BE: dutch unless the store is french' => [
                'BE', ['nl-BE', 'nl-BE', 'nl-BE', 'fr-BE', 'nl-BE', 'nl-BE'],
            ],
            'CH: german, french or italian' => [
                'CH', ['de-CH', 'de-CH', 'de-CH', 'fr-CH', 'de-CH', 'it-CH'],
            ],
            'LU: debtor map, german or french' => [
                'LU', ['de-LU', 'de-LU', 'de-LU', 'fr-LU', 'de-LU', 'de-LU'],
            ],
            'CA: debtor map, english or french' => [
                'CA', ['en-CA', 'en-CA', 'en-CA', 'fr-CA', 'en-CA', 'en-CA'],
            ],
            'US: one culture whatever the store' => [
                'US', ['en-US', 'en-US', 'en-US', 'en-US', 'en-US', 'en-US'],
            ],
            'NA: rejected en-NA degrades to en' => [
                'NA', ['en', 'en', 'en', 'en', 'en', 'en'],
            ],
            'CG: rejected fr-CG degrades to fr' => [
                'CG', ['fr', 'fr', 'fr', 'fr', 'fr', 'fr'],
            ],
            'XK: not curated, no header' => [
                'XK', [null, null, null, null, null, null],
            ],
            'AQ: not curated, no header' => [
                'AQ', [null, null, null, null, null, null],
            ],
            'empty country: no header' => [
                '', [null, null, null, null, null, null],
            ],
        ];
    }

    /**
     * The header takes a culture, never a bare country or language code.
     */
    public function testNeverEmitsACountryCode(): void
    {
        foreach (array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES) as $country) {
            $culture = $this->resolver->resolveForHeader($country, null);

            if (isset(CultureCodeResolver::HEADER_CULTURE_FALLBACKS[(string)$culture])
                || in_array($culture, CultureCodeResolver::HEADER_CULTURE_FALLBACKS, true)
            ) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^[a-z]{2}-[A-Z]{2}$/',
                (string)$culture,
                sprintf('Country %s produced a malformed header culture', $country)
            );
        }
    }

    /**
     * BTI-1578: the header validator rejects these outright with a 400, so no billing
     * country and no locale hint may produce one.
     */
    public function testNeverEmitsAGatewayRejectedCulture(): void
    {
        $countries = array_merge(
            array_keys(CultureCodeResolver::COUNTRY_CULTURES),
            array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES),
            ['NA', 'CG', 'XK', 'ZZ', '']
        );
        $hints = [null, 'en_US', 'nl_NL', 'fr_FR', 'af_ZA', 'en_NA', 'fr_CG'];

        $emitted = [];
        foreach ($countries as $country) {
            foreach ($hints as $hint) {
                $emitted[] = $this->resolver->resolveForHeader($country, $hint);
            }
        }

        $this->assertGreaterThan(300, count($emitted));
        $this->assertNotContains('en-NA', $emitted);
        $this->assertNotContains('fr-CG', $emitted);
        $this->assertSame('en', $this->resolver->resolveForHeader('NA', 'en_NA'));
        $this->assertSame('fr', $this->resolver->resolveForHeader('CG', 'fr_CG'));
    }

    /**
     * The exclusion is header-only: Credit Management accepts en-NA, so the debtor
     * path must keep sending it.
     */
    public function testExclusionDoesNotReachTheDebtorPath(): void
    {
        $this->assertSame('en-NA', $this->resolver->resolveForDebtor('NA', 'nl_NL'));
        $this->assertSame('fr-CG', $this->resolver->resolveForDebtor('CG', 'fr_FR'));
    }

    /**
     * The substitute must keep the country's own language, never jump to en-GB and
     * never silently borrow another country's culture.
     */
    public function testRejectedCultureDegradesToItsOwnLanguage(): void
    {
        foreach (CultureCodeResolver::HEADER_CULTURE_FALLBACKS as $rejected => $fallback) {
            $this->assertSame(
                strtok($rejected, '-'),
                $fallback,
                sprintf('%s should degrade to its own language, not to %s', $rejected, $fallback)
            );
        }

        $this->assertSame('en', $this->resolver->resolveForHeader('NA', 'nl_NL'));
        $this->assertSame('fr', $this->resolver->resolveForHeader('CG', 'nl_NL'));
    }
}
