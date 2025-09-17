<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class ApplepayTest extends BaseTest
{
    // Keep untyped to match BaseTest
    protected $instanceClass = Applepay::class;

    public static function configProvider(): array
    {
        return [
            'active'   => [true],
            'inactive' => [false],
        ];
    }

    #[DataProvider('configProvider')]
    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testGetConfig(bool $active): void
    {
        // 1) Scope config
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            [
                $this->getPaymentMethodConfigPath(Applepay::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                $active,
            ],
            [
                $this->getPaymentMethodConfigPath(Applepay::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR',
            ],
        ]);

        // 2) StoreManager -> Store -> Currency
        $storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getCurrentCurrency'])
            ->getMock();

        $currency = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();

        if ($active) {
            $currency->expects($this->atLeastOnce())
                ->method('getCode')
                ->willReturn('EUR');

            $store->expects($this->atLeastOnce())
                ->method('getName')
                ->willReturn('Buckaroo Webshop');

            $store->expects($this->atLeastOnce())
                ->method('getCurrentCurrency')
                ->willReturn($currency);

            $storeManager->expects($this->atLeastOnce())
                ->method('getStore')
                ->willReturn($store);
        } else {
            $currency->expects($this->never())->method('getCode');
            $store->expects($this->never())->method('getName');
            $store->expects($this->never())->method('getCurrentCurrency');
            $storeManager->expects($this->never())->method('getStore');
        }

        // 3) Locale resolver
        $localeResolver = $this->createMock(Resolver::class);
        if ($active) {
            $localeResolver->expects($this->atLeastOnce())
                ->method('getLocale')
                ->willReturn('nl_NL');
        } else {
            $localeResolver->expects($this->never())->method('getLocale');
        }

        // 4) Account (merchant GUID) — some envs may not use it; just stub the method without asserting calls
        $account = $this->getMockBuilder(\Buckaroo\Magento2\Model\ConfigProvider\Account::class)
            ->disableOriginalConstructor()
            ->addMethods(['getMerchantGuid'])
            ->getMock();
        $account->method('getMerchantGuid')->willReturn('GUID12345');

        // Instance under test
        $instance = $this->getInstance([
            'scopeConfig'    => $scopeConfig,
            'storeManager'   => $storeManager,
            'localeResolver' => $localeResolver,
            'account'        => $account,
        ]);

        $result = $instance->getConfig();

        if (!$active) {
            // Inactive path returns empty array
            $this->assertSame([], $result);
            return;
        }

        // Active path: assert structure & key fields
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey(Applepay::CODE, $result['payment']['buckaroo']);

        $applepay = $result['payment']['buckaroo'][Applepay::CODE];
        $this->assertIsArray($applepay);

        // Required key checks (exact matches)
        $this->assertArrayHasKey('allowedCurrencies', $applepay);
        $this->assertSame(['EUR'], $applepay['allowedCurrencies']);

        $this->assertArrayHasKey('storeName', $applepay);
        $this->assertSame('Buckaroo Webshop', $applepay['storeName']);

        $this->assertArrayHasKey('currency', $applepay);
        $this->assertSame('EUR', $applepay['currency']);

        $this->assertArrayHasKey('cultureCode', $applepay);
        $this->assertSame('nl', $applepay['cultureCode']);

        // Optional: ensure 'guid' key exists; value may be null or a string depending on env/config
        $this->assertArrayHasKey('guid', $applepay);
        $this->assertTrue($applepay['guid'] === null || is_string($applepay['guid']));
    }
}
