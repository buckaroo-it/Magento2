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
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\MockObject\MockObject;

class CurrencyDataBuilderTest extends BaseTest
{
    /**
     * @var MockObject|Factory
     */
    private $configProviderMethodFactoryMock;

    /**
     * @var CurrencyDataBuilder
     */
    private $currencyDataBuilder;

    protected $paymentMethodInstanceMock;
    protected $orderMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->paymentMethodInstanceMock = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $this->orderMock = $this->getFakeMock(\Magento\Sales\Model\Order::class)->getMock();
        $this->configProviderMethodFactoryMock = $this->createMock(Factory::class);
        $this->currencyDataBuilder = new CurrencyDataBuilder($this->configProviderMethodFactoryMock);
    }

    /**
     * @dataProvider currencyDataProvider
     * @param mixed $orderCurrencyCode
     * @param mixed $baseCurrencyCode
     * @param mixed $paymentMethodCode
     * @param mixed $allowedCurrencies
     * @param mixed $expectedResult
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

        $configProviderMock = $this->getMockBuilder(AbstractConfigProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllowedCurrencies'])
            ->getMockForAbstractClass();
        $configProviderMock->method('getAllowedCurrencies')->willReturn($allowedCurrencies);

        $this->configProviderMethodFactoryMock->expects($this->atMost(1))
            ->method('get')
            ->with($paymentMethodCode)
            ->willReturn($configProviderMock);

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

    /**
     * Create PaymentDataObject mock
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getPaymentDOMock()
    {
        // Use the already prepared order mock so currency codes are available
        $orderAdapterMock = $this->getMockBuilder(\Magento\Payment\Gateway\Data\OrderAdapterInterface::class)
            ->addMethods(['getOrder'])
            ->getMockForAbstractClass();
        $orderAdapterMock->method('getOrder')->willReturn($this->orderMock);

        $paymentMock = $this->createMock(\Magento\Payment\Model\InfoInterface::class);
        // Ensure method instance is available for setAllowedCurrencies()
        $paymentMock->method('getMethodInstance')->willReturn($this->paymentMethodInstanceMock);

        $paymentDOMock = $this->createMock(\Magento\Payment\Gateway\Data\PaymentDataObjectInterface::class);
        $paymentDOMock->method('getOrder')->willReturn($orderAdapterMock);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);
        
        return $paymentDOMock;
    }

    public static function currencyDataProvider()
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
