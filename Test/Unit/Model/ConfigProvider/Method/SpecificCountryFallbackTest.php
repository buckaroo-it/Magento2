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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Test\Unit\Stubs\MethodConfigProviderStub;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Finding #23.
 *
 * An empty store-scoped country list used to be silently replaced by the default scope's list, so
 * a merchant who deliberately cleared it for a website or store view kept the global restriction -
 * the method vanished from that store's checkout with no error and no log line. The rescue now
 * only applies when that scope actually restricts countries.
 *
 * The second defect in the same two methods was that they read the bare field name
 * ("specificcountry") as if it were a whole config path, so both returned nothing whatever the
 * merchant had configured and the country gate was dead code. Both are covered below.
 */
class SpecificCountryFallbackTest extends TestCase
{
    private function provider(?string $scoped, ?string $default, $allowSpecific): AbstractConfigProvider
    {
        $provider = $this->getMockBuilder(MethodConfigProviderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethodConfigValue', 'getAllowSpecific'])
            ->getMock();
        // The rescue read deliberately drops the store, so the store argument is what tells the
        // two reads apart: null means "default scope". Anything other than the country field is
        // not this method's business and must not answer.
        $provider->method('getMethodConfigValue')->willReturnCallback(
            static function ($field, $store = null) use ($scoped, $default) {
                if ($field !== AbstractConfigProvider::SPECIFIC_COUNTRY) {
                    return null;
                }

                return $store === null ? $default : $scoped;
            }
        );
        $provider->method('getAllowSpecific')->willReturn($allowSpecific);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($default);

        $ref = new \ReflectionProperty(
            \Buckaroo\Magento2\Model\ConfigProvider\AbstractConfigProvider::class,
            'scopeConfig'
        );
        $ref->setAccessible(true);
        $ref->setValue($provider, $scopeConfig);

        return $provider;
    }

    public function testUsesTheStoreScopedListWhenItIsSet(): void
    {
        $this->assertSame(['BE'], $this->provider('BE', 'NL', '1')->getSpecificCountry(2));
    }

    public function testRescuesAnEmptyListFromTheDefaultScopeOnlyWhenCountriesAreRestricted(): void
    {
        $this->assertSame(['NL'], $this->provider('', 'NL', '1')->getSpecificCountry(2));
    }

    public function testDoesNotInheritTheDefaultListWhenTheScopeDoesNotRestrictCountries(): void
    {
        $this->assertSame([], $this->provider('', 'NL', '0')->getSpecificCountry(2));
    }

    public function testReturnsAnEmptyListWhenNothingIsConfiguredAnywhere(): void
    {
        $this->assertSame([], $this->provider('', '', '1')->getSpecificCountry(2));
    }

    public function testSplitsAMultiCountryList(): void
    {
        $this->assertSame(['NL', 'BE', 'DE'], $this->provider('NL,BE,DE', null, '1')->getSpecificCountry(2));
    }

    /**
     * The reader used to hand the bare field name to the config, i.e. ask for the path
     * "specificcountry" instead of "payment/buckaroo_magento2_<code>/specificcountry". Verified
     * against this environment before the fix: getAllowSpecific() returned NULL while
     * payment/buckaroo_magento2_ideal/allowspecific was really "0". Both methods have to go
     * through the method path pattern, and carry the store, or the gate silently never fires.
     */
    public function testReadsTheMethodScopedPathAndNotTheBareFieldName(): void
    {
        $seen = [];

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function ($path, $scope = null, $store = null) use (&$seen) {
                $seen[] = [$path, $scope, $store];

                return $path === 'payment/buckaroo_magento2_ideal/allowspecific' ? '1' : 'NL';
            }
        );

        $provider = new MethodConfigProviderStub($scopeConfig, 'buckaroo_magento2_ideal');

        $this->assertSame(['NL'], $provider->getSpecificCountry(2));

        $paths = array_column($seen, 0);
        $this->assertContains('payment/buckaroo_magento2_ideal/specificcountry', $paths);
        $this->assertNotContains('specificcountry', $paths, 'the bare field name must never be used as a path');

        foreach ($seen as [$path, $scope, $store]) {
            $this->assertSame(ScopeInterface::SCOPE_STORE, $scope, "$path was not read in store scope");
        }
        $this->assertContains(2, array_column($seen, 2), 'the store has to be carried into the read');
    }
}
