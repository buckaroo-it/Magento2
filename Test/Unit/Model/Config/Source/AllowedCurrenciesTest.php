<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source;

use Magento\Framework\Locale\Bundle\CurrencyBundle;
use TIG\Buckaroo\Model\Config\Source\AllowedCurrencies;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies as AllowedCurrenciesConfig;

class AllowedCurrenciesTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = AllowedCurrencies::class;

    /**
     * Test what happens when there is no value provided.
     */
    public function testToOptionArray()
    {
        $currenciesConfigMock = $this->getFakeMock(AllowedCurrenciesConfig::class)
            ->setMethods(['getAllowedCurrencies'])
            ->getMock();
        $currenciesConfigMock->expects($this->once())->method('getAllowedCurrencies')->willReturn(['USD', 'EUR']);

        $currenctBundleData = [
            'Currencies' => [
                'USD' => [1 => 'US Dollar'],
                'EUR' => [1 => 'Euro']
            ]
        ];

        $currencyBundleMock = $this->getFakeMock(CurrencyBundle::class)->setMethods(['get'])->getMock();
        $currencyBundleMock->expects($this->once())->method('get')->willReturn($currenctBundleData);

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
