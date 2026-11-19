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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\Config\Source\GooglepayButtonType;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class GooglepayTest extends BaseTest
{
    // Keep untyped to match BaseTest
    protected $instanceClass = Googlepay::class;

    public static function configuredTypeProvider(): array
    {
        return [
            'offered type'       => ['donate', 'donate'],
            'config.xml default' => ['buy', 'buy'],
            'unknown value'      => ['long', 'buy'],
        ];
    }

    /**
     * The configured button type reaches the key pay.js reads, normalised.
     *
     * @param string $stored
     * @param string $expected
     */
    #[DataProvider('configuredTypeProvider')]
    public function testGetConfigPassesTheButtonType(string $stored, string $expected): void
    {
        $values = [
            'payment/buckaroo_magento2_googlepay/active'      => '1',
            'payment/buckaroo_magento2_googlepay/button_type' => $stored,
        ];
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function ($path) use ($values) {
                return $values[$path] ?? null;
            }
        );

        $currency = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $currency->method('getCode')->willReturn('EUR');

        $store = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName', 'getCurrentCurrency'])
            ->getMock();
        $store->method('getName')->willReturn('Buckaroo Webshop');
        $store->method('getCurrentCurrency')->willReturn($currency);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $localeResolver = $this->createMock(Resolver::class);
        $localeResolver->method('getLocale')->willReturn('nl_NL');

        $instance = $this->getInstance([
            'scopeConfig'    => $scopeConfig,
            'storeManager'   => $storeManager,
            'localeResolver' => $localeResolver,
            'buttonTypes'    => new GooglepayButtonType(),
        ]);

        $googlepay = $instance->getConfig()['payment']['buckaroo'][Googlepay::CODE];

        $this->assertSame($expected, $googlepay['buttonType']);
    }

    public function testGetConfigIsEmptyWhenInactive(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('0');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfig, 'buttonTypes' => new GooglepayButtonType()]);

        $this->assertSame([], $instance->getConfig());
    }

    public static function buttonTypeProvider(): array
    {
        // Fallback rules are covered in GooglepayButtonTypeTest; this checks path and delegation
        return [
            'offered type'        => ['subscribe', 'subscribe'],
            'not configured'      => [null, 'buy'],
            'apple value'         => ['check-out', 'buy'],
        ];
    }

    /**
     * @param mixed  $stored
     * @param string $expected
     */
    #[DataProvider('buttonTypeProvider')]
    public function testGetButtonTypeFallsBackToBuy($stored, string $expected): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('payment/buckaroo_magento2_googlepay/button_type', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($stored);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfig, 'buttonTypes' => new GooglepayButtonType()]);

        $this->assertSame($expected, $instance->getButtonType());
    }
}
