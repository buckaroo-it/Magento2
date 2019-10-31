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
namespace TIG\Buckaroo\Test\Unit\Model\Method\Capayable;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\Method\Capayable\Installments;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;
use TIG\Buckaroo\Test\BaseTest;

class InstallmentsTest extends BaseTest
{
    protected $instanceClass = Installments::class;

    public function testGetCode()
    {
        $instance = $this->getInstance();
        $result = $instance->getCode();

        $this->assertEquals('tig_buckaroo_capayablein3', $result);
    }

    public function testPaymentMethodCode()
    {
        $instance = $this->getInstance();
        $result = $instance->buckarooPaymentMethodCode;

        $this->assertEquals('capayablein3', $result);
    }

    public function testServiceAction()
    {
        $instance = $this->getInstance();
        $result = $instance::CAPAYABLE_ORDER_SERVICE_ACTION;

        $this->assertEquals('PayInInstallments', $result);
    }

    /**
     * @return array
     */
    public function getCapayableServiceProvider()
    {
        return [
            'in3 flexible' => [
                '0',
                ['_' => 'false', 'Name' => 'IsInThreeGuarantee']
            ],
            'in3 garant' => [
                '1',
                ['_' => 'true', 'Name' => 'IsInThreeGuarantee']
            ]
        ];
    }

    /**
     * @param $version
     * @param $expected
     *
     * @dataProvider getCapayableServiceProvider
     */
    public function testGetCapayableService($version, $expected)
    {
        $billingAddressMock = $this->getFakeMock(Address::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getBillingAddress', 'getAllItems'])->getMock();
        $orderMock->method('getBillingAddress')->willReturn($billingAddressMock);
        $orderMock->method('getAllItems')->willReturn([]);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $softwareDataMock = $this->getFakeMock(SoftwareData::class)
            ->setMethods(['getProductMetaData', 'getEdition'])
            ->getMock();
        $softwareDataMock->expects($this->once())->method('getProductMetaData')->willReturnSelf();
        $softwareDataMock->expects($this->once())->method('getEdition')->willReturn('Community');

        $factoryMock = $this->getFakeMock(Factory::class)->setMethods(['get', 'getVersion'])->getMock();
        $factoryMock->expects($this->once())->method('get')->with('capayablein3')->willReturnSelf();
        $factoryMock->expects($this->once())->method('getVersion')->willReturn($version);

        $instance = $this->getInstance([
            'softwareData' => $softwareDataMock,
            'configProviderMethodFactory' => $factoryMock
        ]);

        $result = $instance->getCapayableService($paymentMock);
        $garantVersionResult = $result['RequestParameter'][count($result['RequestParameter']) - 1];

        $this->assertEquals($expected, $garantVersionResult);
    }
}
