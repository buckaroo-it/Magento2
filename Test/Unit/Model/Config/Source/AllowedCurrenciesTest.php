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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;

use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Buckaroo\Magento2\Model\Config\Source\AllowedCurrencies;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies as AllowedCurrenciesConfig;

class AllowedCurrenciesTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    /**
     * Test what happens when there is no value provided.
     */
    public function testToOptionArray()
    {
        $currenciesConfigMock = $this->getFakeMock(AllowedCurrenciesConfig::class)
            ->onlyMethods(["getAllowedCurrencies"])
            ->getMock();
        $currenciesConfigMock->method('getAllowedCurrencies')->willReturn(['USD', 'EUR']);

        $currenctBundleData = [
            'Currencies' => [
                'USD' => [1 => 'US Dollar'],
                'EUR' => [1 => 'Euro']
            ]
        ];

        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->onlyMethods(['get'])->getMock();
        $currencyBundleMock->method('get')->willReturn($currenctBundleData);

        $instance = $this->getInstance([
            'allowedCurrenciesConfig' => $currenciesConfigMock,'currencyBundle' => $currencyBundleMock
        ]);
        $result = $instance->toOptionArray();

        $this->assertTrue(is_array($result));
        $this->assertEquals(2, count($result));

        $expectedResult = [
            ['value' => 'USD', 'label' => 'US Dollar'],
            ['value' => 'EUR', 'label' => 'Euro']
        ];

        $this->assertEquals($expectedResult, $result);
    }
}
