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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Request\BasicParameter\CurrencyDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use PHPUnit\Framework\MockObject\MockObject;

class CurrencyDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|Factory
     */
    private $configProviderMethodFactoryMock;

    /**
     * @var CurrencyDataBuilder
     */
    private $currencyDataBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configProviderMethodFactoryMock = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->currencyDataBuilder = new CurrencyDataBuilder($this->configProviderMethodFactoryMock);
    }

    /**
     * @dataProvider currencyDataProvider
     */
    public function testBuild(
        $orderCurrencyCode,
        $baseCurrencyCode,
        $paymentMethodCode,
        $allowedCurrencies,
        $expectedResult
    ) {
        $this->orderMock->method('getOrderCurrencyCode')->willReturn($orderCurrencyCode);
        $this->orderMock->method('getBaseCurrencyCode')->willReturn($baseCurrencyCode);

        $this->paymentMethodInstanceMock->expects($this->atMost(1))
            ->method('getCode')
            ->willReturn($paymentMethodCode);

        $configProvider = $this->getMockBuilder(ConfigProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider->expects($this->atMost(1))
            ->method('getAllowedCurrencies')
            ->willReturn($allowedCurrencies);

        $this->configProviderMethodFactoryMock->expects($this->atMost(1))
            ->method('get')
            ->with($paymentMethodCode)
            ->willReturn($configProvider);

        if ($expectedResult instanceof \Buckaroo\Magento2\Exception) {
            $this->expectExceptionObject($expectedResult);
        }

        $this->assertEquals(
            $expectedResult,
            $this->currencyDataBuilder->build([
                'payment' => $this->getPaymentDOMock()
            ])
        );
    }

    public function currencyDataProvider()
    {
        return [
            [
                'EUR', 'USD', 'buckaroo_magento_ideal', ['EUR'],
                [CurrencyDataBuilder::KEY_CURRENCY => 'EUR']
            ],
            [
                'GBP', 'EUR', 'buckaroo_magento_ideal', ['USD', 'GBP'],
                [CurrencyDataBuilder::KEY_CURRENCY => 'GBP']
            ],
            [
                'GBP', 'EUR', 'buckaroo_magento_ideal', ['USD', 'EUR'],
                [CurrencyDataBuilder::KEY_CURRENCY => 'EUR']
            ],
            [
                'GBP', 'EUR', null, ['USD', 'GBP'],
                new \Buckaroo\Magento2\Exception(__("The payment method code it is not set."))
            ],
            [
                'JPY', 'EUR', 'buckaroo_magento_ideal', ['USD', 'GBP'],
                new \Buckaroo\Magento2\Exception(
                // @codingStandardsIgnoreLine
                    __("The selected payment method does not support the selected currency or the store's base currency.")
                ),
            ],
            [
                'USD', 'EUR', 'buckaroo_magento_ideal', [],
                new \Buckaroo\Magento2\Exception(
                // @codingStandardsIgnoreLine
                    __("The selected payment method does not support the selected currency or the store's base currency.")
                ),
            ],
        ];
    }
}
