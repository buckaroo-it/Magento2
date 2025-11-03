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

namespace Buckaroo\Magento2\Test\Unit\Service\CreditManagement\ServiceParameters;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Service\CreditManagement\ServiceParameters\CreateCombinedInvoice;
use Buckaroo\Magento2\Test\BaseTest;

class CreateCombinedInvoiceTest extends BaseTest
{
    protected $instanceClass = CreateCombinedInvoice::class;

    public function testGet()
    {
        $payPerMailConfigMock = $this->getFakeMock(PayPerEmail::class)
            ->onlyMethods(['getSchemeKey', 'getActiveStatusCm3', 'getCm3DueDate', 'getMaxStepIndex', 'getPaymentMethod', 'getPaymentMethodAfterExpiry'])
            ->getMock();
        $payPerMailConfigMock->method('getSchemeKey')->willReturn('abc');
        $payPerMailConfigMock->method('getActiveStatusCm3')->willReturn(true);

        $factoryMock = $this->getFakeMock(Factory::class)->onlyMethods(['get'])->getMock();
        $factoryMock->method('get')->with('payperemail')->willReturn($payPerMailConfigMock);

        // Add more required methods to the PayPerEmail mock
        $payPerMailConfigMock->method('getCm3DueDate')->willReturn(30);
        $payPerMailConfigMock->method('getMaxStepIndex')->willReturn(3);
        $payPerMailConfigMock->method('getPaymentMethod')->willReturn('ideal,sepadirectdebit');
        $payPerMailConfigMock->method('getPaymentMethodAfterExpiry')->willReturn('sepadirectdebit');

        $addressMock = $this->getFakeMock(Address::class)
            ->onlyMethods(['getEmail', 'getTelephone', 'getFirstname', 'getLastname', 'getCountryId', 'getStreet', 'getPostcode', 'getCity', 'getCompany'])
            ->getMock();
        $addressMock->method('getEmail')->willReturn('test@example.com');
        $addressMock->method('getTelephone')->willReturn('0612345678');
        $addressMock->method('getFirstname')->willReturn('John');
        $addressMock->method('getLastname')->willReturn('Doe');
        $addressMock->method('getCountryId')->willReturn('NL');
        $addressMock->method('getStreet')->willReturn(['Kabelweg 37']);
        $addressMock->method('getPostcode')->willReturn('1014BA');
        $addressMock->method('getCity')->willReturn('Amsterdam');
        $addressMock->method('getCompany')->willReturn('');

        $orderMock = $this->getFakeMock(Order::class)
            ->onlyMethods(['getBillingAddress', 'getGrandTotal', 'getTaxAmount', 'getPayment'])
            ->getMock();
        $orderMock->method('getBillingAddress')->willReturn($addressMock);
        $orderMock->method('getGrandTotal')->willReturn(100.00);
        $orderMock->method('getTaxAmount')->willReturn(21.00);

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getAdditionalInformation', 'getOrder', 'getMethod'])
            ->getMock();
        $infoInstanceMock->method('getAdditionalInformation')->willReturn(null);
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);
        $infoInstanceMock->method('getMethod')->willReturn('buckaroo_magento2_payperemail');

        // Set up the circular reference for order payment
        $orderMock->method('getPayment')->willReturn($infoInstanceMock);

        $instance = $this->getInstance(['configProviderMethodFactory' => $factoryMock]);
        $result = $instance->get($infoInstanceMock, 'payperemail');

        $this->assertIsArray($result);
        $this->assertEquals('CreditManagement3', $result['Name']);
        $this->assertEquals('CreateCombinedInvoice', $result['Action']);
        $this->assertEquals(1, $result['Version']);
        $this->assertArrayHasKey('RequestParameter', $result);
        $this->assertIsArray($result['RequestParameter']);
        $this->assertGreaterThan(10, count($result['RequestParameter'])); // At least 10 parameters expected

        $possibleParameters = [
            'Code', 'Email', 'Mobile', 'InvoiceAmount', 'InvoiceAmountVAT', 'InvoiceDate', 'DueDate',
            'SchemeKey', 'MaxStepIndex', 'AllowedServices', 'AllowedServicesAfterDueDate', 'Culture',
            'FirstName', 'LastName', 'Gender', 'Street', 'HouseNumber', 'Zipcode', 'City', 'Country'
        ];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertTrue(in_array($array['Name'], $possibleParameters));
        }
    }

    public static function getCmAddressProvider()
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
