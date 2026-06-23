<?php
declare(strict_types=1);

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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

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

    /**
     * @dataProvider configProvider
     */
    public function testGetConfig(bool $active): void
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

        $storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getCurrentCurrency', 'getBaseUrl', 'getConfig'])
            ->getMock();

        $currency = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();

        if ($active) {
            $currency->method('getCode')->willReturn('EUR');
            $store->method('getName')->willReturn('Test Store');
            $store->method('getCurrentCurrency')->willReturn($currency);
            $store->method('getBaseUrl')->willReturn('https://example.com/');
            $store->method('getConfig')->willReturn('NL');
            $storeManager->method('getStore')->willReturn($store);
        } else {
            $storeManager->expects($this->never())->method('getStore');
        }

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

        $this->assertArrayHasKey('clientId', $clicktopay);
        $this->assertSame('test-client-id', $clicktopay['clientId']);

        $this->assertArrayHasKey('clientSecret', $clicktopay);
        $this->assertSame('test-client-secret', $clicktopay['clientSecret']);

        $this->assertArrayHasKey('merchantIdentifier', $clicktopay);
        $this->assertSame('MERCHANT-GUID-TEST', $clicktopay['merchantIdentifier']);

        $this->assertArrayHasKey('allowedCurrencies', $clicktopay);
        $this->assertSame(['EUR'], $clicktopay['allowedCurrencies']);

        $this->assertArrayHasKey('storeName', $clicktopay);
        $this->assertArrayHasKey('currency', $clicktopay);
        $this->assertArrayHasKey('locale', $clicktopay);
        $this->assertArrayHasKey('targetOrigins', $clicktopay);
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
