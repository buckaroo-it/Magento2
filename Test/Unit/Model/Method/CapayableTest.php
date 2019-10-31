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
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Capayable;
use TIG\Buckaroo\Service\Formatter\AddressFormatter;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;
use TIG\Buckaroo\Test\BaseTest;

class CapayableTest extends BaseTest
{
    protected $instanceClass = Capayable::class;

    public function testAssignData()
    {
        $data = $this->getObject(DataObject::class);
        $data->setBuckarooSkipValidation(0);
        $data->setAdditionalData([
            'customer_gender' => 'male',
            'customer_DoB' => '01/05/1990',
            'customer_orderAs' => 'company',
            'customer_cocnumber' => '12345678',
            'customer_companyName' => 'TIG',
        ]);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['setAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInstanceMock->expects($this->exactly(6))->method('setAdditionalInformation')->withConsecutive(
            ['buckaroo_skip_validation', 0],
            ['customer_gender', 'male'],
            ['customer_DoB', '1990-05-01'],
            ['customer_orderAs', 'company'],
            ['customer_cocnumber', '12345678'],
            ['customer_companyName', 'TIG']
        );

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($data);
        $this->assertInstanceOf(Capayable::class, $result);
    }

    public function getRequestParameterRowProvider()
    {
        return [
            'value and name' => [
                ['someValue', 'someName'],
                [
                    '_' => 'someValue',
                    'Name' => 'someName'
                ]
            ],
            'value, name and grouptype' => [
                ['someValue', 'someName', 'someGroup'],
                [
                    '_' => 'someValue',
                    'Name' => 'someName',
                    'Group' => 'someGroup'
                ]
            ],
            'value, name and groupid' => [
                ['someValue', 'someName', null, 1],
                [
                    '_' => 'someValue',
                    'Name' => 'someName',
                    'GroupID' => 1
                ]
            ],
            'value, name, grouptype and groupid' => [
                ['someValue', 'someName', 'someGroup', 2],
                [
                    '_' => 'someValue',
                    'Name' => 'someName',
                    'Group' => 'someGroup',
                    'GroupID' => 2
                ]
            ],
        ];
    }

    /**
     * @param $args
     * @param $expected
     *
     * @dataProvider getRequestParameterRowProvider
     */
    public function testGetRequestParameterRow($args, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('getRequestParameterRow', $args, $instance);

        $this->assertEquals($expected, $result);
    }

    public function testGetOrderTransactionBuilder()
    {
        $billingAddressMock = $this->getFakeMock(Address::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getBillingAddress', 'getAllItems'])->getMock();
        $orderMock->method('getBillingAddress')->willReturn($billingAddressMock);
        $orderMock->method('getAllItems')->willReturn([]);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder', 'setAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('setAdditionalInformation')->with('skip_push', 1);

        $softwareDataMock = $this->getFakeMock(SoftwareData::class)
            ->setMethods(['getProductMetaData', 'getEdition'])
            ->getMock();
        $softwareDataMock->expects($this->once())->method('getProductMetaData')->willReturnSelf();
        $softwareDataMock->expects($this->once())->method('getEdition')->willReturn('Community');

        $factoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setServices', 'setMethod'])
            ->getMock();
        $factoryMock->expects($this->once())->method('get')->with('order')->willReturnSelf();
        $factoryMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $factoryMock->expects($this->once())->method('setServices')->willReturnSelf();
        $factoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();

        $instance = $this->getInstance([
            'softwareData' => $softwareDataMock,
            'transactionBuilderFactory' => $factoryMock
        ]);

        $result = $instance->getOrderTransactionBuilder($paymentMock);
        $this->assertEquals($factoryMock, $result);
    }

    public function testGetCapayableService()
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

        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $result = $instance->getCapayableService($paymentMock);

        $this->assertInternalType('array', $result);
        $this->assertEquals('capayable', $result['Name']);
        $this->assertEquals('', $result['Action']);
        $this->assertInternalType('array', $result['RequestParameter']);
        $this->assertGreaterThan(1, $result['RequestParameter']);
    }

    public function testGetCustomerData()
    {
        $billingAddressMock = $this->getFakeMock(Address::class)->setMethods(['getStreet'])->getMock();
        $billingAddressMock->expects($this->once())->method('getStreet')->willReturn('Kabelweg 37 D');

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getBillingAddress'])->getMock();
        $orderMock->expects($this->exactly(2))->method('getBillingAddress')->willReturn($billingAddressMock);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->exactly(2))->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')
            ->withConsecutive(['customer_orderAs'], ['customer_gender'], ['customer_DoB'], ['customer_orderAs'])
            ->willReturnOnConsecutiveCalls(2, 'male', '1990-01-01', 2);

        $addressFormatterMock = $this->getFakeMock(AddressFormatter::class)
            ->setMethods(['formatTelephone', 'formatStreet'])
            ->getMock();
        $addressFormatterMock->expects($this->once())->method('formatTelephone')->willReturn(['clean' => '0201122233']);
        $addressFormatterMock->expects($this->once())
            ->method('formatStreet')
            ->with('Kabelweg 37 D')
            ->willReturn(['street' => 'Kabelweg', 'house_number' => '37', 'number_addition' => 'D']);

        $instance = $this->getInstance(['addressFormatter' => $addressFormatterMock]);
        $result = $this->invokeArgs('getCustomerData', [$paymentMock], $instance);

        $expectedDataNames = [
            'CustomerType', 'InvoiceDate', 'Phone', 'Email', 'Initials', 'LastName', 'Culture', 'Gender', 'BirthDate',
            'Street', 'HouseNumber', 'HouseNumberSuffix', 'ZipCode', 'City', 'Country', 'Name', 'ChamberOfCommerce'
        ];

        $this->assertInternalType('array', $result);

        foreach ($result as $dataRow) {
            $this->assertArrayHasKey('_', $dataRow);
            $this->assertArrayHasKey('Name', $dataRow);
            $this->assertContains($dataRow['Name'], $expectedDataNames);
        }
    }

    public function testGetProductLineData()
    {
        $orderItemMock = $this->getFakeMock(Item::class)->getMock();

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getAllItems'])->getMock();
        $orderMock->expects($this->once())->method('getAllItems')->willReturn([$orderItemMock, []]);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $this->invokeArgs('getProductLineData', [$paymentMock], $instance);

        $expectedDataNames = ['Code', 'Name', 'Quantity', 'Price'];

        $this->assertInternalType('array', $result);

        foreach ($result as $dataRow) {
            $this->assertArrayHasKey('_', $dataRow);
            $this->assertArrayHasKey('Name', $dataRow);
            $this->assertContains($dataRow['Name'], $expectedDataNames);
        }
    }

    public function testGetSubtotalLineData()
    {
        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getDiscountAmount', 'getCustomerBalanceAmount', 'getBuckarooFee', 'getShippingInclTax'])
            ->getMock();
        $orderMock->expects($this->once())->method('getDiscountAmount')->willReturn(1);
        $orderMock->expects($this->once())->method('getCustomerBalanceAmount')->willReturn(2);
        $orderMock->expects($this->once())->method('getBuckarooFee')->willReturn(3);
        $orderMock->expects($this->once())->method('getShippingInclTax')->willReturn(4);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $softwareDataMock = $this->getFakeMock(SoftwareData::class)
            ->setMethods(['getProductMetaData', 'getEdition'])
            ->getMock();
        $softwareDataMock->expects($this->once())->method('getProductMetaData')->willReturnSelf();
        $softwareDataMock->expects($this->once())->method('getEdition')->willReturn('Enterprise');

        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $result = $this->invokeArgs('getSubtotalLineData', [$paymentMock], $instance);

        $this->assertInternalType('array', $result);

        $expectedNames = ['Name', 'Value'];
        $expectedValues = ['Korting', 'Betaaltoeslag', 'Verzendkosten', -3, 3, 4];

        $this->assertInternalType('array', $result);

        foreach ($result as $dataRow) {
            $this->assertArrayHasKey('_', $dataRow);
            $this->assertContains($dataRow['_'], $expectedValues);
            $this->assertArrayHasKey('Name', $dataRow);
            $this->assertContains($dataRow['Name'], $expectedNames);
        }
    }

    public function testGetCaptureTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance();
        $result = $instance->getCaptureTransactionBuilder($paymentMock);

        $this->assertFalse($result);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance();
        $result = $instance->getAuthorizeTransactionBuilder($paymentMock);

        $this->assertFalse($result);
    }

    public function testGetRefundTransactionBuilder()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_original_transaction_key')
            ->willReturn('123abc');
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $factoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setServices', 'setMethod', 'setOriginalTransactionKey'])
            ->getMock();
        $factoryMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $factoryMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $factoryMock->expects($this->once())
            ->method('setServices')
            ->with(['Name' => 'capayable', 'Action' => 'Refund'])
            ->willReturnSelf();
        $factoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $factoryMock->expects($this->once())->method('setOriginalTransactionKey')->with('123abc')->willReturnSelf();

        $instance = $this->getInstance(['transactionBuilderFactory' => $factoryMock]);
        $result = $instance->getRefundTransactionBuilder($paymentMock);

        $this->assertEquals($factoryMock, $result);
    }

    public function testGetVoidTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance();
        $result = $instance->getVoidTransactionBuilder($paymentMock);

        $this->assertTrue($result);
    }
}
