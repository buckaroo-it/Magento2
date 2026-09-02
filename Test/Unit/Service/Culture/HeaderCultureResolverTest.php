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
     */
    public function testOnlyEverEmitsACuratedCulture(): void
    {
        $curated = array_merge(
            ...array_values(CultureCodeResolver::COUNTRY_CULTURES),
            ...array_values(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES)
        );

        $countries = array_merge(
            array_keys(CultureCodeResolver::COUNTRY_CULTURES),
            array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES),
            ['XK', 'AQ', 'ZZ', '']
        );

        foreach ($countries as $country) {
            foreach ([null, 'en_US', 'nl_NL', 'fr_FR'] as $hint) {
                $culture = $this->resolver->resolveForHeader($country, $hint);

                if ($culture === null) {
                    continue;
                }

                $this->assertContains(
                    $culture,
                    $curated,
                    sprintf('Uncurated culture %s emitted for %s', $culture, $country)
                );
            }
        }
    }

    /**
     * The header takes a culture, never a bare country or language code.
     */
    public function testNeverEmitsACountryCode(): void
    {
        foreach (array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES) as $country) {
            $culture = $this->resolver->resolveForHeader($country, null);

            $this->assertMatchesRegularExpression(
                '/^[a-z]{2}-[A-Z]{2}$/',
                (string)$culture,
                sprintf('Country %s produced a malformed header culture', $country)
            );
        }
    }
}
