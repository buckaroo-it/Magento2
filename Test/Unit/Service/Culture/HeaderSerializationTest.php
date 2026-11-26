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

use Buckaroo\BuckarooClient;
use Buckaroo\Config\Config;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Buckaroo\Services\TransactionHeaders\CultureHeader;
use Buckaroo\Services\TransactionHeaders\DefaultHeader;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;
use PHPUnit\Framework\TestCase;

/**
 * Proves the resolved culture actually reaches the wire as a "Culture:" header.
 *
 * The unit tests next door stop at the resolver's return value. Issue #1761 turns on
 * what is *serialised*, so these go one layer further: the SDK config is built by the
 * real BuckarooAdapter::setClientSdk() and fed to the real SDK header stack.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class HeaderSerializationTest extends TestCase
{
    /**
     * Build the SDK config through BuckarooAdapter::setClientSdk().
     *
     * Only the adapter's collaborators are mocked, so passing the culture in the wrong
     * DefaultConfig argument makes the header fall back to the SDK default.
     *
     * @param string|null $culture     culture override handed to the adapter
     * @param string      $storeLocale locale of order store 3, used when there is no override
     *
     * @return Config
     */
    private function adapterConfig(?string $culture, string $storeLocale = ''): Config
    {
        $account = $this->createMock(Account::class);
        $account->method('getActive')->willReturn(1);
        $account->method('getMerchantKey')->willReturn('encrypted-website-key');
        $account->method('getSecretKey')->willReturn('encrypted-secret-key');
        $account->method('getHttpTimeout')->willReturn(30);
        $account->method('getHttpConnectTimeout')->willReturn(10);

        $configProviderFactory = $this->createMock(ConfigProviderFactory::class);
        $configProviderFactory->method('get')->with('account')->willReturn($account);

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn ($value) => 'decrypted-' . $value);

        $productMetadata = $this->createMock(ProductMetadataInterface::class);
        $productMetadata->method('getName')->willReturn('Magento');
        $productMetadata->method('getEdition')->willReturn('Community');
        $productMetadata->method('getVersion')->willReturn('2.4.9');

        $localeResolver = $this->createMock(Resolver::class);
        $localeResolver->method('emulate')->with(3)->willReturn($storeLocale);
        $localeResolver->method('getLocale')->willReturn('');

        $adapter = (new \ReflectionClass(BuckarooAdapter::class))->newInstanceWithoutConstructor();
        $properties = [
            'configProviderFactory' => $configProviderFactory,
            'encryptor'             => $encryptor,
            'logger'                => $this->createMock(BuckarooLoggerInterface::class),
            'productMetadata'       => $productMetadata,
            'localeResolver'        => $localeResolver,
        ];
        foreach ($properties as $name => $value) {
            (new \ReflectionProperty(BuckarooAdapter::class, $name))->setValue($adapter, $value);
        }

        // Store 3, post-transaction call on a test-mode transaction: no payment method lookup.
        (new \ReflectionMethod(BuckarooAdapter::class, 'setClientSdk'))
            ->invoke($adapter, '', 3, true, true, $culture);

        $client = (new \ReflectionProperty(BuckarooAdapter::class, 'buckaroo'))->getValue($adapter);
        $this->assertInstanceOf(BuckarooClient::class, $client);

        return (new \ReflectionProperty(BuckarooClient::class, 'config'))->getValue($client);
    }

    /**
     * The "Culture: <value>" line the SDK would put on the request.
     *
     * @param string|null $culture
     * @param string      $storeLocale
     *
     * @return string|null
     */
    private function serialisedCultureHeader(?string $culture, string $storeLocale = ''): ?string
    {
        $config = $this->adapterConfig($culture, $storeLocale);
        $headers = (new CultureHeader(new DefaultHeader([]), $config))->getHeaders();

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
        $this->assertSame('Culture: nl-NL', $this->serialisedCultureHeader('nl-NL', 'de_DE'));
        $this->assertSame('Culture: en-ZA', $this->serialisedCultureHeader('en-ZA', 'de_DE'));
    }

    /**
     * Without an override the order store's locale is sent, in header notation.
     */
    public function testNoOverrideSerialisesTheOrderStoreLocale(): void
    {
        $this->assertSame('Culture: nl-BE', $this->serialisedCultureHeader(null, 'nl_BE'));
    }

    /**
     * Without an override or a store locale the SDK's own default is sent, never an empty header.
     */
    public function testNoCultureAtAllFallsBackToTheSdkDefault(): void
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
            static function ($culture) {
                return 'Culture: ' . $culture;
            },
            array_keys(CultureCodeResolver::HEADER_CULTURE_FALLBACKS)
        );

        $countries = array_merge(
            array_keys(CultureCodeResolver::COUNTRY_CULTURES),
            array_keys(CultureCodeResolver::DEBTOR_COUNTRY_CULTURES)
        );
        $this->assertNotEmpty($countries);
        $this->assertNotEmpty($rejected);

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
