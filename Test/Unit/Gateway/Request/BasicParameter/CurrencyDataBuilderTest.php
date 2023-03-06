<?php

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\BasicParameter\CurrencyDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CurrencyDataBuilderTest extends TestCase
{
    /** @var MockObject|Factory */
    private $configProviderMethodFactoryMock;

    /** @var MockObject|Order */
    private $orderMock;

    /** @var CurrencyDataBuilder */
    private $currencyDataBuilder;

    protected function setUp(): void
    {
        $this->configProviderMethodFactoryMock = $this->getMockBuilder(Factory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock = $this->getMockBuilder(Order::class)
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

        $paymentDOMock = $this->getMockBuilder(PaymentDataObjectInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter = $this->getMockBuilder(OrderAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderAdapter->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->orderMock);

        $paymentDOMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($orderAdapter);

        $infoInterface = $this->getMockBuilder(InfoInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMethodInstanceMock = $this->getMockBuilder(MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMethodInstanceMock->expects($this->atMost(1))
            ->method('getCode')
            ->willReturn($paymentMethodCode);

        $infoInterface->expects($this->atMost(1))
            ->method('getMethodInstance')
            ->willReturn($paymentMethodInstanceMock);

        $paymentDOMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($infoInterface);

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

        $buildSubject = [
            'payment' => $paymentDOMock
        ];

        if ($expectedResult instanceof \Buckaroo\Magento2\Exception) {
            $this->expectExceptionObject($expectedResult);
        }

        $this->assertEquals($expectedResult, $this->currencyDataBuilder->build($buildSubject));
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
