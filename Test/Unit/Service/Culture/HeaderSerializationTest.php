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

use Buckaroo\Config\DefaultConfig;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Buckaroo\Services\TransactionHeaders\CultureHeader;
use Buckaroo\Services\TransactionHeaders\DefaultHeader;
use PHPUnit\Framework\TestCase;

/**
 * Proves the resolved culture actually reaches the wire as a "Culture:" header.
 *
 * The unit tests next door stop at the resolver's return value. Issue #1761 turns on
 * what is *serialised*, so these go one layer further and drive the real SDK header
 * stack — the same classes BuckarooAdapter::setClientSdk() feeds in production.
 */
class HeaderSerializationTest extends TestCase
{
    /**
     * Build the SDK config exactly as BuckarooAdapter::setClientSdk() does.
     *
     * Culture is the 13th positional argument. Passing it anywhere else silently
     * lands it in another field, so pinning the position is the point of this helper.
     *
     * @param string|null $culture
     *
     * @return DefaultConfig
     */
    private function adapterConfig(?string $culture): DefaultConfig
    {
        return new DefaultConfig(
            'websiteKey',
            'secretKey',
            'test',
            null,           // currency
            null,           // returnURL
            null,           // returnURLCancel
            null,           // pushURL
            'Magento - Community',
            '2.4.9',
            'Buckaroo',
            'Magento2',
            '2.7.1',
            $culture,
            null,
            null,
            null
        );
    }

    /**
     * The "Culture: <value>" line the SDK would put on the request.
     *
     * @param string|null $culture
     *
     * @return string|null
     */
    private function serialisedCultureHeader(?string $culture): ?string
    {
        $headers = (new CultureHeader(new DefaultHeader([]), $this->adapterConfig($culture)))->getHeaders();

        foreach ($headers as $header) {
            if (strpos($header, 'Culture: ') === 0) {
                return $header;
            }
        }

        return null;
    }

    /**
     * The culture argument must land in the Culture header, not in a neighbouring field.
     */
    public function testResolvedCultureIsSerialisedVerbatim(): void
    {
        $this->assertSame('Culture: nl-NL', $this->serialisedCultureHeader('nl-NL'));
        $this->assertSame('Culture: en-ZA', $this->serialisedCultureHeader('en-ZA'));
    }

    /**
     * Omitting the override must leave the SDK's own default, never an empty header.
     */
    public function testNullCultureFallsBackToTheSdkDefault(): void
    {
        $this->assertSame('Culture: en-GB', $this->serialisedCultureHeader(null));
    }

    /**
     * The end-to-end guarantee for issue #1761: no billing country may serialise a
     * header the gateway rejects. The rejected set is gateway-verified (BTI-1578).
     */
    public function testNoBillingCountrySerialisesARejectedHeader(): void
    {
        $resolver = new CultureCodeResolver();

        $rejected = array_map(
            static fn(string $culture): string => 'Culture: ' . $culture,
            array_keys(CultureCodeResolver::HEADER_CULTURE_FALLBACKS)
        );

        $countries = array_merge(
            array_keys(CultureCodeResolver::COUNTRY_CULTURES),
            array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES)
        );

        foreach ($countries as $country) {
            foreach ([null, 'en_US', 'nl_NL', 'fr_FR', 'af_ZA', 'de_DE'] as $hint) {
                $culture = $resolver->resolveForHeader($country, $hint);

                if ($culture === null) {
                    continue;
                }

                $this->assertNotContains(
                    $this->serialisedCultureHeader($culture),
                    $rejected,
                    sprintf('Country %s / locale %s serialised a rejected header', $country, (string)$hint)
                );
            }
        }
    }

    /**
     * Namibia is the reported failure: it must no longer serialise en-NA.
     */
    public function testNamibiaNoLongerSerialisesEnNa(): void
    {
        $resolver = new CultureCodeResolver();

        foreach ([null, 'en_US', 'nl_NL', 'af_ZA'] as $hint) {
            $this->assertSame(
                'Culture: en',
                $this->serialisedCultureHeader($resolver->resolveForHeader('NA', $hint)),
                sprintf('Namibia on locale %s', (string)$hint)
            );
        }
    }

    /**
     * South Africa was reported as failing too, but the gateway accepts en-ZA. It must
     * keep its own culture rather than be swept up in the Namibia fix.
     */
    public function testSouthAfricaKeepsEnZa(): void
    {
        $resolver = new CultureCodeResolver();

        $this->assertSame(
            'Culture: en-ZA',
            $this->serialisedCultureHeader($resolver->resolveForHeader('ZA', 'en_US'))
        );
    }
}
