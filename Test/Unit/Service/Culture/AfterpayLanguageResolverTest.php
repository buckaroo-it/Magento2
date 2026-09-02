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

use Buckaroo\Magento2\Service\Culture\AfterpayLanguageResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The legacy Afterpay builder's "culture" key is renamed to "Language" by the SDK,
 * so it must carry a language code from the supported set - never a country code.
 */
class AfterpayLanguageResolverTest extends TestCase
{
    /**
     * @var AfterpayLanguageResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AfterpayLanguageResolver();
    }

    #[DataProvider('languageProvider')]
    public function testResolvesLanguage(?string $country, ?string $hint, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($country, $hint));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function languageProvider(): array
    {
        return [
            // The defect: Belgium used to send "BE", which is not a language.
            'belgium defaults to dutch'   => ['BE', null, 'NL'],
            'belgium honours french hint' => ['BE', 'fr_BE', 'FR'],
            'belgium ignores german hint' => ['BE', 'de_DE', 'NL'],

            // Countries where the old country-code behaviour happened to be valid.
            'netherlands'                 => ['NL', null, 'NL'],
            'germany'                     => ['DE', null, 'DE'],
            'france'                      => ['FR', null, 'FR'],
            'finland'                     => ['FI', null, 'FI'],

            'austria speaks german'       => ['AT', null, 'DE'],
            'switzerland honours french'  => ['CH', 'fr_CH', 'FR'],
            'lowercase country'           => ['be', 'fr_BE', 'FR'],

            'unsupported country'         => ['ZA', null, AfterpayLanguageResolver::DEFAULT_LANGUAGE],
            'empty country'               => ['', null, AfterpayLanguageResolver::DEFAULT_LANGUAGE],
            'null country'                => [null, null, AfterpayLanguageResolver::DEFAULT_LANGUAGE],
        ];
    }

    /**
     * Whatever the input, the result must be a value the field accepts.
     */
    public function testAlwaysReturnsASupportedLanguage(): void
    {
        $countries = array_merge(
            array_keys(AfterpayLanguageResolver::COUNTRY_LANGUAGES),
            ['ZA', 'NA', 'US', 'GB', 'XX', '']
        );

        foreach ($countries as $country) {
            foreach ([null, 'nl_NL', 'fr_BE', 'en_US'] as $hint) {
                $this->assertContains(
                    $this->resolver->resolve($country, $hint),
                    AfterpayLanguageResolver::SUPPORTED_LANGUAGES,
                    sprintf('Unsupported language for country "%s", hint "%s"', $country, (string)$hint)
                );
            }
        }
    }

    /**
     * Countries outside the supported set must not have their code echoed back.
     */
    public function testUnsupportedCountryCodeIsNotEchoedBack(): void
    {
        foreach (['ZA', 'NA', 'US', 'GB', 'IT', 'ES'] as $country) {
            $this->assertNotSame(
                $country,
                $this->resolver->resolve($country, null),
                sprintf('Country %s leaked into the Language field', $country)
            );
        }
    }
}
