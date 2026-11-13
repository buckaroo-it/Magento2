<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Clicktopay;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ClicktopayTest extends BaseTest
{
    protected $instanceClass = Clicktopay::class;

    public static function configProvider(): array
    {
        return [
            'active'   => [true],
            'inactive' => [false],
        ];
    }

    #[DataProvider('configProvider')]
    public function testGetConfig(bool $active): void
    {
        $scopeConfig    = $this->createScopeConfigMock($active);
        $storeManager   = $this->createStoreManagerMock($active);

        $localeResolver = $this->createMock(Resolver::class);
        if ($active) {
            $localeResolver->method('getLocale')->willReturn('nl_NL');
        } else {
            $localeResolver->expects($this->never())->method('getLocale');
        }

        $instance = $this->getInstance([
            'scopeConfig'    => $scopeConfig,
            'storeManager'   => $storeManager,
            'localeResolver' => $localeResolver,
        ]);

        $result = $instance->getConfig();

        if (!$active) {
            $this->assertSame([], $result);
            return;
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey(Clicktopay::CODE, $result['payment']['buckaroo']);

        $clicktopay = $result['payment']['buckaroo'][Clicktopay::CODE];
        $this->assertIsArray($clicktopay);

        // Credentials must never be exposed to the frontend; they are decrypted and used
        // server-side only by the Clicktopay\Token proxy controller.
        $this->assertArrayNotHasKey('clientId', $clicktopay);
        $this->assertArrayNotHasKey('clientSecret', $clicktopay);

        $this->assertArrayHasKey('merchantIdentifier', $clicktopay);
        $this->assertSame('MERCHANT-GUID-TEST', $clicktopay['merchantIdentifier']);

        $this->assertArrayHasKey('allowedCurrencies', $clicktopay);
        $this->assertSame(['EUR'], $clicktopay['allowedCurrencies']);

        $this->assertArrayHasKey('storeName', $clicktopay);
        $this->assertArrayHasKey('currency', $clicktopay);
        $this->assertArrayHasKey('locale', $clicktopay);
        $this->assertArrayHasKey('targetOrigins', $clicktopay);
    }

    /**
     * @param bool $active
     * @return ScopeConfigInterface
     */
    private function createScopeConfigMock(bool $active): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            [
                $this->getPaymentMethodConfigPath(Clicktopay::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                $active,
            ],
            [
                $this->getPaymentMethodConfigPath(Clicktopay::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR',
            ],
            [
                $this->getPaymentMethodConfigPath(Clicktopay::CODE, Clicktopay::XPATH_CLICKTOPAY_CLIENT_ID),
                ScopeInterface::SCOPE_STORE,
                null,
                'test-client-id',
            ],
            [
                $this->getPaymentMethodConfigPath(Clicktopay::CODE, Clicktopay::XPATH_CLICKTOPAY_CLIENT_SECRET),
                ScopeInterface::SCOPE_STORE,
                null,
                'test-client-secret',
            ],
            [
                $this->getPaymentMethodConfigPath(Clicktopay::CODE, Clicktopay::XPATH_CLICKTOPAY_MERCHANT_IDENTIFIER),
                ScopeInterface::SCOPE_STORE,
                null,
                'MERCHANT-GUID-TEST',
            ],
        ]);

        return $scopeConfig;
    }

    /**
     * @param bool $active
     * @return StoreManagerInterface
     */
    private function createStoreManagerMock(bool $active): StoreManagerInterface
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);

        if (!$active) {
            $storeManager->expects($this->never())->method('getStore');

            return $storeManager;
        }

        $currency = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $currency->method('getCode')->willReturn('EUR');

        $store = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getCurrentCurrency', 'getBaseUrl', 'getConfig'])
            ->getMock();
        $store->method('getName')->willReturn('Test Store');
        $store->method('getCurrentCurrency')->willReturn($currency);
        $store->method('getBaseUrl')->willReturn('https://example.com/');
        $store->method('getConfig')->willReturn('NL');

        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
    }

    public function testGetBaseAllowedCurrencies(): void
    {
        $instance = $this->getInstance([]);
        $currencies = $instance->getBaseAllowedCurrencies();

        $this->assertIsArray($currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertGreaterThanOrEqual(3, count($currencies));
    }
}
