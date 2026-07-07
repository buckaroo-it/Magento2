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
use PHPUnit\Framework\TestCase;

class CultureCodeResolverTest extends TestCase
{
    /**
     * @var CultureCodeResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CultureCodeResolver();
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(?string $country, ?string $localeHint, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($country, $localeHint));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function resolveProvider(): array
    {
        return [
            // The reported bug: Belgium order on a Dutch store must be nl-BE, not nl-NL.
            'BE dutch store'             => ['BE', 'nl_NL', 'nl-BE'],
            'BE french browser'          => ['BE', 'fr_BE', 'fr-BE'],
            'BE french store'            => ['BE', 'fr_FR', 'fr-BE'],
            'BE english store'           => ['BE', 'en_US', 'en-BE'],
            'BE no hint -> primary'      => ['BE', null, 'nl-BE'],
            'BE unknown lang -> primary' => ['BE', 'pl_PL', 'nl-BE'],

            // Netherlands
            'NL default'                 => ['NL', 'nl_NL', 'nl-NL'],
            'NL english'                 => ['NL', 'en_US', 'en-NL'],

            // Switzerland (four supported languages)
            'CH german'                  => ['CH', 'de_CH', 'de-CH'],
            'CH french'                  => ['CH', 'fr_FR', 'fr-CH'],
            'CH italian'                 => ['CH', 'it_IT', 'it-CH'],
            'CH no hint -> primary'      => ['CH', null, 'de-CH'],

            // Single-language / requested countries
            'DE'                         => ['DE', 'de_DE', 'de-DE'],
            'AT'                         => ['AT', 'de_AT', 'de-AT'],
            'FR'                         => ['FR', 'fr_FR', 'fr-FR'],
            'IT'                         => ['IT', 'it_IT', 'it-IT'],
            'ES'                         => ['ES', 'es_ES', 'es-ES'],
            'PT'                         => ['PT', 'pt_PT', 'pt-PT'],
            'PL'                         => ['PL', 'pl_PL', 'pl-PL'],
            'FI finnish'                 => ['FI', 'fi_FI', 'fi-FI'],
            'FI swedish'                 => ['FI', 'sv_SE', 'sv-FI'],
            'GB'                         => ['GB', 'en_GB', 'en-GB'],
            'SE'                         => ['SE', 'sv_SE', 'sv-SE'],
            'NO'                         => ['NO', 'nb_NO', 'nb-NO'],
            'DK'                         => ['DK', 'da_DK', 'da-DK'],

            // Country id casing is normalized
            'lowercase country'          => ['be', 'fr_BE', 'fr-BE'],

            // Unmapped country: honour a valid locale hint, else default
            'unmapped valid hint'        => ['LU', 'de_DE', 'de-DE'],
            'unmapped unknown hint'      => ['LU', 'xx_YY', CultureCodeResolver::DEFAULT_CULTURE],
            'unmapped no hint'           => ['LU', null, CultureCodeResolver::DEFAULT_CULTURE],

            // Missing / empty billing country
            'empty country no hint'      => ['', null, CultureCodeResolver::DEFAULT_CULTURE],
            'null country'               => [null, null, CultureCodeResolver::DEFAULT_CULTURE],
        ];
    }

    public function testEveryResolvedCultureIsWhitelisted(): void
    {
        $supported = $this->resolver->getSupportedCultures();

        foreach (array_keys(CultureCodeResolver::COUNTRY_CULTURES) as $country) {
            foreach (['nl', 'fr', 'de', 'it', 'en', 'sv', null] as $lang) {
                $hint = $lang === null ? null : $lang . '_XX';
                $this->assertContains(
                    $this->resolver->resolve($country, $hint),
                    $supported,
                    sprintf('Culture for %s / %s must be whitelisted', $country, (string)$lang)
                );
            }
        }
    }
}
