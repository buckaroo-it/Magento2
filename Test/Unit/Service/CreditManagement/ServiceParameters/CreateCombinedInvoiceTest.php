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
namespace TIG\Buckaroo\Test\Unit\Service\CreditManagement\ServiceParameters;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\PayPerEmail;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters\CreateCombinedInvoice;
use TIG\Buckaroo\Test\BaseTest;

class CreateCombinedInvoiceTest extends BaseTest
{
    protected $instanceClass = CreateCombinedInvoice::class;

    public function testGet()
    {
        $payPerMailConfigMock = $this->getFakeMock(PayPerEmail::class)
            ->setMethods(['getSchemeKey', 'getActiveStatusCm3'])
            ->getMock();
        $payPerMailConfigMock->expects($this->once())->method('getSchemeKey')->willReturn('abc');
        $payPerMailConfigMock->expects($this->once())->method('getActiveStatusCm3')->willReturn(true);

        $factoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $factoryMock->expects($this->once())->method('get')->with('payperemail')->willReturn($payPerMailConfigMock);

        $addressMock = $this->getFakeMock(Address::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getBillingAddress'])
            ->getMock();
        $orderMock->method('getBillingAddress')->willReturn($addressMock);

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getAdditionalInformation', 'getOrder'])
            ->getMock();
        $infoInstanceMock->expects($this->once())->method('getAdditionalInformation');
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance(['configProviderMethodFactory' => $factoryMock]);
        $result = $instance->get($infoInstanceMock, 'payperemail');

        $this->assertInternalType('array', $result);
        $this->assertEquals('CreditManagement3', $result['Name']);
        $this->assertEquals('CreateCombinedInvoice', $result['Action']);
        $this->assertEquals(1, $result['Version']);
        $this->assertCount(20, $result['RequestParameter']);

        $possibleParameters = [
            'Code', 'Email', 'Mobile', 'InvoiceAmount', 'InvoiceAmountVAT', 'InvoiceDate', 'DueDate',
            'SchemeKey', 'MaxStepIndex', 'AllowedServices', 'AllowedServicesAfterDueDate', 'Culture',
            'FirstName', 'LastName', 'Gender', 'Street', 'HouseNumber', 'Zipcode', 'City', 'Country'
        ];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertContains($array['Name'], $possibleParameters);
        }
    }

    public function getCmAddressProvider()
    {
        return [
            'only street' => [
                'Kabelweg',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '',
                    'number_addition' => '',
                ]
            ],
            'with house number' => [
                'Kabelweg 37',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => '',
                ]
            ],
            'with house number addition' => [
                'Kabelweg 37A',
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => 'A',
                ]
            ],
            'as array' => [
                ['Kabel', 'Weg 37'],
                [
                    'street'          => 'Kabel Weg',
                    'house_number'    => '37',
                    'number_addition' => '',
                ]
            ],
        ];
    }

    /**
     * @param $address
     * @param $expected
     *
     * @dataProvider getCmAddressProvider
     */
    public function testGetCmAddress($address, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('getCmAddress', [$address], $instance);

        $this->assertEquals($expected, $result);
    }
}
