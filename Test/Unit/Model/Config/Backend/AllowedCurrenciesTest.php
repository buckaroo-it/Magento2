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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Buckaroo\Magento2\Model\Config\Backend\AllowedCurrencies;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies as AllowedCurrenciesProvider;

class AllowedCurrenciesTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    /**
     * @param array $allowed
     *
     * @return AllowedCurrenciesProvider
     */
    private function getConfigProviderMock(array $allowed)
    {
        $configProviderMock = $this->getFakeMock(AllowedCurrenciesProvider::class)
            ->onlyMethods(["getAllowedCurrencies"])
            ->getMock();
        $configProviderMock->method('getAllowedCurrencies')->willReturn($allowed);

        return $configProviderMock;
    }

    /**
     * @param int $expectedSaves
     *
     * @return \Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub
     */
    private function getResourceMock(int $expectedSaves)
    {
        $resourceMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\AbstractResourceStub::class)
            ->onlyMethods(['save'])->getMock();
        $resourceMock->expects($this->exactly($expectedSaves))->method('save');

        return $resourceMock;
    }

    /**
     * Without a value nothing has to be validated and the config is saved.
     */
    public function testSaveNoValue()
    {
        $instance = $this->getInstance([
            'configProvider' => $this->getConfigProviderMock(['EUR', 'USD']),
            'resource' => $this->getResourceMock(1),
        ]);

        $result = $instance->save();
        $this->assertSame($instance, $result);
        $this->assertNull($instance->getValue());
    }

    /**
     * Allowed currencies are saved unchanged.
     */
    public function testSaveWithValidValue()
    {
        $instance = $this->getInstance([
            'configProvider' => $this->getConfigProviderMock(['EUR', 'USD']),
            'resource' => $this->getResourceMock(1),
        ]);
        $instance->setValue(['EUR', 'USD']);

        $result = $instance->save();
        $this->assertSame($instance, $result);
        $this->assertSame(['EUR', 'USD'], $instance->getValue());
    }

    /**
     * A currency that is not allowed is rejected with its translated name and is not saved.
     */
    public function testSaveWithInvalidValue()
    {
        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->onlyMethods(['get'])->getMock();
        $currencyBundleMock->expects($this->once())->method('get')->with('en_US')->willReturn([
            'Currencies' => [
                'GBP' => [0, 'British Pound Sterling', 'GBP']
            ]
        ]);

        $localeResolverMock = $this->getFakeMock(\Magento\Framework\Locale\ResolverInterface::class)->getMock();
        $localeResolverMock->method('getLocale')->willReturn('en_US');

        $instance = $this->getInstance([
            'configProvider' => $this->getConfigProviderMock(['EUR', 'USD']),
            'currencyBundle' => $currencyBundleMock,
            'localeResolver' => $localeResolverMock,
            'resource' => $this->getResourceMock(0),
        ]);
        $instance->setValue(['EUR', 'GBP']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Please enter a valid currency: 'British Pound Sterling'.");

        $instance->save();
    }

    /**
     * A currency that is not allowed and has no translation is reported by its code.
     */
    public function testSaveWithInvalidValueWithoutTranslation()
    {
        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->onlyMethods(['get'])->getMock();
        $currencyBundleMock->method('get')->willReturn([
            'Currencies' => [
                'GBP' => [1 => 'British Pound']
            ]
        ]);

        $localeResolverMock = $this->getFakeMock(\Magento\Framework\Locale\ResolverInterface::class)->getMock();
        $localeResolverMock->method('getLocale')->willReturn('en_US');

        $instance = $this->getInstance([
            'configProvider' => $this->getConfigProviderMock(['EUR', 'USD']),
            'currencyBundle' => $currencyBundleMock,
            'localeResolver' => $localeResolverMock,
            'resource' => $this->getResourceMock(0),
        ]);
        $instance->setValue(['CHF']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Please enter a valid currency: 'CHF'.");

        $instance->save();
    }

    /**
     * The translated name is taken from index 1 of the currency bundle entry.
     */
    public function testSaveWithInvalidValueThatHasCurrency()
    {
        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->onlyMethods(['get'])->getMock();
        $currencyBundleMock->method('get')->willReturn([
            'Currencies' => [
                'GBP' => [1 => 'British Pound']
            ]
        ]);

        $localeResolverMock = $this->getFakeMock(\Magento\Framework\Locale\ResolverInterface::class)->getMock();
        $localeResolverMock->method('getLocale')->willReturn('en_US');

        $instance = $this->getInstance([
            'currencyBundle' => $currencyBundleMock,
            'configProvider' => $this->getConfigProviderMock(['EUR', 'USD']),
            'localeResolver' => $localeResolverMock,
            'resource' => $this->getResourceMock(0),
        ]);
        $instance->setValue(['GBP']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("Please enter a valid currency: 'British Pound'.");

        $instance->save();
    }
}
