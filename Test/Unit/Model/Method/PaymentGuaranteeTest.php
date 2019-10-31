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

use Magento\Framework\App\Config;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice as MagentoInvoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as TransactionBuilderOrder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\PaymentGuarantee as ConfigProviderPaymentGuarantee;
use TIG\Buckaroo\Model\Invoice;
use TIG\Buckaroo\Model\InvoiceFactory;
use TIG\Buckaroo\Model\Method\PaymentGuarantee;
use TIG\Buckaroo\Service\Formatter\Address\StreetFormatter;
use TIG\Buckaroo\Service\Formatter\AddressFormatter;
use TIG\Buckaroo\Test\BaseTest;

class PaymentGuaranteeTest extends BaseTest
{
    protected $instanceClass = PaymentGuarantee::class;

    /**
     * @return array
     */
    public function assignDataProvider()
    {
        return [
            'no data' => [
                []
            ],
            'correct DoB dateformat' => [
                [
                    'additional_data' => [
                        'termsCondition' => '0',
                        'customer_gender' => 'female',
                        'customer_billingName' => 'TIG',
                        'customer_DoB' => '07/10/1990',
                        'customer_iban' => 'BE67890'
                    ]
                ]
            ],
            'incorrect DoB dateformat' => [
                [
                    'additional_data' => [
                        'termsCondition' => '1',
                        'customer_gender' => 'female',
                        'customer_billingName' => 'TIG',
                        'customer_DoB' => '1990-01-01',
                        'customer_iban' => 'NL65498'
                    ]
                ]
            ],
        ];
    }

    /**
     * @param $data
     *
     * @dataProvider assignDataProvider
     */
    public function testAssignData($data)
    {
        $dataObject = $this->getObject(DataObject::class);
        $dataObject->addData($data);

        $instance = $this->getInstance();

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)->getMock();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($dataObject);
        $this->assertInstanceOf(PaymentGuarantee::class, $result);
    }

    /**
     * @return array
     */
    public function canCaptureProvider()
    {
        return [
            'can capture' => [
                'capture',
                true
            ],
            'can not capture' => [
                'order',
                false
            ]
        ];
    }

    /**
     * @param $paymentAction
     * @param $expected
     *
     * @dataProvider canCaptureProvider
     */
    public function testCanCapture($paymentAction, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(Config::class)->setMethods(['getValue'])->getMock();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn($paymentAction);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->canCapture();

        $this->assertEquals($expected, $result);
    }

    public function testGetOrderTransactionBuilder()
    {
        $orderMock = $this->getOrderMock();

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getTransactionInstance();
        $result = $instance->getOrderTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderOrder::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals('TransactionRequest', $result->getMethod());

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('paymentguarantee', $services['Name']);
        $this->assertEquals('PaymentInvitation', $services['Action']);
    }

    public function testGetCaptureTransactionBuilder()
    {
        $orderMock = $this->getOrderMock();

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getTransactionInstance();
        $result = $instance->getCaptureTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderOrder::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals('TransactionRequest', $result->getMethod());

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('paymentguarantee', $services['Name']);
        $this->assertEquals('PartialInvoice', $services['Action']);
        $this->assertArrayHasKey('RequestParameter', $services);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $orderMock = $this->getOrderMock();

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getTransactionInstance();
        $result = $instance->getAuthorizeTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderOrder::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals('TransactionRequest', $result->getMethod());

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('paymentguarantee', $services['Name']);
        $this->assertEquals('Order', $services['Action']);
        $this->assertArrayHasKey('RequestParameter', $services);
    }

    public function testGetVoidTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class)->getMock();
        $instance = $this->getInstance();

        $result = $instance->getVoidTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testAfterCapture()
    {
        $invoiceMock = $this->getFakeMock(Invoice::class)->getMock();

        $invoiceFactoryMock = $this->getFakeMock(InvoiceFactory::class)->setMethods(['create'])->getMock();
        $invoiceFactoryMock->expects($this->once())->method('create')->willReturn($invoiceMock);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)->getMock();
        $responseArray = [
            (Object)[
                'Invoice' => '123',
                'Key' => 'abc'
            ]
        ];

        $instance = $this->getInstance(['invoiceFactory' => $invoiceFactoryMock]);
        $result = $this->invokeArgs('afterCapture', [$infoInstanceMock, $responseArray], $instance);

        $this->assertInstanceOf(PaymentGuarantee::class, $result);
    }

    public function testGetPaymentGuaranteeRequestParameters()
    {
        $orderMock = $this->getOrderMock();

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getTransactionInstance();
        $result = $this->invokeArgs('getPaymentGuaranteeRequestParameters', [$paymentMock], $instance);

        $this->assertInternalType('array', $result);
        $this->assertGreaterThanOrEqual(19, count($result));
        $this->assertArrayHasKey('_', $result[0]);
        $this->assertArrayHasKey('Name', $result[0]);
    }

    /**
     * @return array
     */
    public function singleAddressProvider()
    {
        return [
            'address 1' => [
                [
                    'street' => 'Kabelweg 37',
                    'postcode' => '1014 BA',
                    'city' => 'Amsterdam',
                    'country_id' => 'NL'
                ],
                'INVOICE,SHIPPING',
                1,
                [
                    'AddressType' => 'INVOICE,SHIPPING',
                    'Street' => 'Kabelweg',
                    'HouseNumber' => '37',
                    'ZipCode' => '1014 BA',
                    'City' => 'Amsterdam',
                    'Country' => 'NL',
                ]
            ],
            'address 2' => [
                [
                    'street' => 'Hoofdstraat 80 1',
                    'postcode' => '8441ER',
                    'city' => 'Heerenveen',
                    'country_id' => 'NL'
                ],
                'SHIPPING',
                2,
                [
                    'AddressType' => 'SHIPPING',
                    'Street' => 'Hoofdstraat',
                    'HouseNumber' => '80',
                    'ZipCode' => '8441ER',
                    'City' => 'Heerenveen',
                    'Country' => 'NL',
                ]
            ]
        ];
    }

    /**
     * @param $addressData
     * @param $addressType
     * @param $addressId
     * @param $expected
     *
     * @dataProvider singleAddressProvider
     */
    public function testSingleAddress($addressData, $addressType, $addressId, $expected)
    {
        $streetFormatter = $this->getObject(StreetFormatter::class);
        $addressFormatter = $this->getObject(AddressFormatter::class, ['streetFormatter' => $streetFormatter]);
        $instance = $this->getInstance(['addressFormatter' => $addressFormatter]);

        $address = $this->getObject(Address::class);
        $address->setData($addressData);

        $result = $this->invokeArgs('singleAddress', [$address, $addressType, $addressId], $instance);
        $this->assertEquals('address', $result[0]['Group']);
        $this->assertEquals('address_' . $addressId, $result[0]['GroupID']);

        foreach ($result as $resultItem) {
            $key = $resultItem['Name'];
            $this->assertEquals($expected[$key], $resultItem['_']);
        }
    }

    /**
     * @return array
     */
    public function isAddressDataDifferentProvider()
    {
        return [
            'different arrays' => [
                ['abc'],
                ['def'],
                false,
                true
            ],
            'equal arrays' => [
                ['ghi'],
                ['ghi'],
                false,
                false
            ],
            'different objects' => [
                ['jkl'],
                ['mno'],
                true,
                true
            ],
            'equal objects' => [
                ['pqr'],
                ['pqr'],
                true,
                false
            ],
            'first address is null' => [
                null,
                ['stu'],
                true,
                false
            ],
            'second address is null' => [
                ['vwx'],
                null,
                true,
                false
            ]
        ];
    }

    /**
     * @param $dataOne
     * @param $dataTwo
     * @param $isObject
     * @param $expected
     *
     * @dataProvider isAddressDataDifferentProvider
     */
    public function testIsAddressDataDifferent($dataOne, $dataTwo, $isObject, $expected)
    {
        $addressOne = $dataOne;
        $addessTwo = $dataTwo;

        if ($isObject && $dataOne) {
            $addressOne = $this->getFakeMock(Address::class)->getMock();
            $addressOne->method('getData')->willReturn($dataOne);
        }

        if ($isObject && $dataTwo) {
            $addessTwo = $this->getFakeMock(Address::class)->setMethods(['getData'])->getMock();
            $addessTwo->method('getData')->willReturn($dataTwo);
        }

        $instance = $this->getInstance();
        $result = $this->invokeArgs('isAddressDataDifferent', [$addressOne, $addessTwo], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function calculateInvoiceAmountProvider()
    {
        return [
            'no invoices' => [
                [],
                0
            ],
            'single invoice' => [
                [12.34],
                12.34
            ],
            'multiple invoices' => [
                [
                    56.78,
                    90.12,
                    34.56
                ],
                34.56
            ],
        ];
    }

    /**
     * @param $invoiceAmounts
     * @param $expected
     *
     * @dataProvider calculateInvoiceAmountProvider
     */
    public function testCalculateInvoiceAmount($invoiceAmounts, $expected)
    {
        $invoices = [];

        foreach ($invoiceAmounts as $amount) {
            $invoiceMock = $this->getFakeMock(MagentoInvoice::class)->setMethods(['getBaseGrandTotal'])->getMock();
            $invoiceMock->expects($this->any())->method('getBaseGrandTotal')->willReturn($amount);

            $invoices[] = $invoiceMock;
        }

        $invoiceCollection = $this->objectManagerHelper->getCollectionMock(InvoiceCollection::class, $invoices);
        $invoiceCollection->expects($this->atLeastOnce())->method('count')->willReturn(count($invoices));

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getInvoiceCollection'])->getMock();
        $orderMock->expects($this->any())->method('getInvoiceCollection')->willReturn($invoiceCollection);

        $instance = $this->getInstance();
        $result = $this->invokeArgs('calculateInvoiceAmount', [$orderMock], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function calculateTaxAmountProvider()
    {
        return [
            'order tax' => [
                1.23,
                [],
                null,
                1.23
            ],
            'invoice tax' => [
                2.34,
                [
                    3.45,
                    4.56
                ],
                null,
                4.56
            ],
            'creditmemo tax' => [
                5.67,
                [
                    6.78,
                    7.89
                ],
                8.90,
                8.90
            ],
        ];
    }

    /**
     * @param $orderTax
     * @param $invoiceTaxes
     * @param $creditmemoTax
     * @param $expected
     *
     * @dataProvider calculateTaxAmountProvider
     */
    public function testCalculateTaxAmount($orderTax, $invoiceTaxes, $creditmemoTax, $expected)
    {
        $invoices = [];

        foreach ($invoiceTaxes as $amount) {
            $invoiceMock = $this->getFakeMock(MagentoInvoice::class)->setMethods(['getBaseTaxAmount'])->getMock();
            $invoiceMock->expects($this->any())->method('getBaseTaxAmount')->willReturn($amount);

            $invoices[] = $invoiceMock;
        }

        $invoiceCollection = $this->objectManagerHelper->getCollectionMock(InvoiceCollection::class, $invoices);
        $invoiceCollection->expects($this->once())->method('count')->willReturn(count($invoices));

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getInvoiceCollection', 'getBaseTaxAmount'])
            ->getMock();
        $orderMock->expects($this->any())->method('getInvoiceCollection')->willReturn($invoiceCollection);
        $orderMock->expects($this->once())->method('getBaseTaxAmount')->willReturn($orderTax);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)->setMethods(['getBaseTaxAmount'])->getMock();
        $creditmemoMock->expects($this->any())->method('getBaseTaxAmount')->willReturn($creditmemoTax);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder', 'getCreditmemo'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getOrder')->willReturn($orderMock);
        $expectsGetCreditmemo = $paymentMock->expects($this->once())->method('getCreditmemo');

        if ($creditmemoTax) {
            $expectsGetCreditmemo->willReturn($creditmemoMock);
        }

        $instance = $this->getInstance();
        $result = $this->invokeArgs('calculateTaxAmount', [$paymentMock], $instance);

        $this->assertInternalType('array', $result);
        $this->assertEquals($expected, $result[0]['_']);
    }

    /**
     * @return array
     */
    public function setCaptureTypeProvider()
    {
        return [
            'different amounts' => [
                1,
                2,
                1,
                true
            ],
            'multiple invoices' => [
                3,
                3,
                2,
                true
            ],
            'multiple invoices and different amounts' => [
                5,
                4,
                3,
                true
            ],
            'one invoice and same amounts' => [
                6,
                6,
                1,
                false
            ],
        ];
    }

    /**
     * @param $orderAmount
     * @param $invoiceAmount
     * @param $invoicesCount
     * @param $expected
     *
     * @dataProvider setCaptureTypeProvider
     */
    public function testSetCaptureType($orderAmount, $invoiceAmount, $invoicesCount, $expected)
    {
        $invoiceCollection = $this->objectManagerHelper->getCollectionMock(InvoiceCollection::class, []);
        $invoiceCollection->expects($this->once())->method('count')->willReturn($invoicesCount);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getBaseGrandTotal', 'getInvoiceCollection'])->getMock();
        $orderMock->expects($this->once())->method('getBaseGrandTotal')->willReturn($orderAmount);
        $orderMock->expects($this->once())->method('getInvoiceCollection')->willReturn($invoiceCollection);

        $instance = $this->getInstance();
        $this->invokeArgs('setCaptureType', [$orderMock, $invoiceAmount], $instance);

        $result = $this->getProperty('_isPartialCapture', $instance);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function isPartialRefundProvider()
    {
        return [
            'different amounts' => [
                1,
                2,
                true
            ],
            'same amounts' => [
                3,
                3,
                false
            ],
        ];
    }

    /**
     * @param $invoiceAmount
     * @param $creditmemoAmount
     * @param $expected
     *
     * @dataProvider isPartialRefundProvider
     */
    public function testIsPartialRefund($invoiceAmount, $creditmemoAmount, $expected)
    {
        $invoiceMock = $this->getFakeMock(MagentoInvoice::class)->setMethods(['getBaseGrandTotal'])->getMock();
        $invoiceMock->expects($this->once())->method('getBaseGrandTotal')->willReturn($invoiceAmount);

        $creditmemoMock = $this->getFakeMock(Creditmemo::class)
            ->setMethods(['getBaseGrandTotal', 'getInvoice'])
            ->getMock();
        $creditmemoMock->expects($this->once())->method('getInvoice')->willReturn($invoiceMock);
        $creditmemoMock->expects($this->once())->method('getBaseGrandTotal')->willReturn($creditmemoAmount);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getCreditmemo'])->getMock();
        $paymentMock->expects($this->atLeastOnce())->method('getCreditmemo')->willReturn($creditmemoMock);

        $instance = $this->getInstance();
        $result = $this->invokeArgs('isPartialRefund', [$paymentMock], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getOrderMock()
    {
        $orderAddressMock = $this->getFakeMock(Address::class)
            ->setMethods(['getFirstName', 'getStreet', 'getData'])
            ->getMock();
        $orderAddressMock->expects($this->any())->method('getData')->willReturn([]);

        $invoiceCollectionMock = $this->objectManagerHelper->getCollectionMock(InvoiceCollection::class, []);
        $creditmemoCollectionMock = $this->objectManagerHelper->getCollectionMock(CreditmemoCollection::class, []);

        $orderMock = $this->getFakeMock(Order::class)->getMock();
        $orderMock->method('getInvoiceCollection')->willReturn($invoiceCollectionMock);
        $orderMock->method('getCreditmemosCollection')->willReturn($creditmemoCollectionMock);
        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($orderAddressMock);
        $orderMock->expects($this->once())->method('getShippingAddress')->willReturn($orderAddressMock);

        return $orderMock;
    }

    /**
     * @return object
     */
    private function getTransactionInstance()
    {
        $transactionOrderMock = $this->getFakeMock(TransactionBuilderOrder::class)
            ->setMethods(['setReturnUrl'])
            ->getMock();
        $transactionOrderMock->method('setReturnUrl')->willReturnSelf();

        $transactionBuilderMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionBuilderMock->method('get')->willReturn($transactionOrderMock);

        $configGuaranteeMock = $this->getFakeMock(ConfigProviderPaymentGuarantee::class)->getMock();

        $configProviderMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $configProviderMock->expects($this->once())->method('get')->willReturn($configGuaranteeMock);

        $transactionInstance = $this->getInstance([
            'transactionBuilderFactory' => $transactionBuilderMock,
            'configProviderMethodFactory' => $configProviderMock
        ]);

        return $transactionInstance;
    }

    /**
     * @return array
     */
    public function getPartialIdProvider()
    {
        return [
            'no invoices, no creditmemo, is not refunding' => [
                0,
                0,
                null,
                1,
                '1-0'
            ],
            'no invoices, no creditmemo, is refunding' => [
                0,
                0,
                'creditmemo',
                2,
                '2-1'
            ],
            'has invoices, no creditmemo, is not refunding' => [
                3,
                0,
                null,
                4,
                '4-3'
            ],
            'has invoices, no creditmemo, is refunding' => [
                5,
                0,
                'creditmemo',
                6,
                '6-6'
            ],
            'no invoices, has creditmemo, is not refunding' => [
                0,
                7,
                null,
                8,
                '8-7'
            ],
            'no invoices, has creditmemo, is refunding' => [
                0,
                9,
                'creditmemo',
                10,
                '10-10'
            ],
            'has invoices, has creditmemo, is not refunding' => [
                11,
                12,
                null,
                13,
                '13-23'
            ],
            'has invoices, has creditmemo, is refunding' => [
                14,
                15,
                'creditmemo',
                16,
                '16-30'
            ],
        ];
    }

    /**
     * @param $invoicesCount
     * @param $creditmemoCount
     * @param $creditMemo
     * @param $id
     * @param $expected
     *
     * @dataProvider getPartialIdProvider
     */
    public function testGetPartialId($invoicesCount, $creditmemoCount, $creditMemo, $id, $expected)
    {
        $invoiceCollection = $this->objectManagerHelper->getCollectionMock(InvoiceCollection::class, []);
        $invoiceCollection->expects($this->once())->method('count')->willReturn($invoicesCount);

        $creditmemoCollection = $this->objectManagerHelper->getCollectionMock(CreditmemoCollection::class, []);
        $creditmemoCollection->expects($this->once())->method('count')->willReturn($creditmemoCount);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getInvoiceCollection', 'getCreditmemosCollection', 'getIncrementId'])
            ->getMock();
        $orderMock->expects($this->once())->method('getInvoiceCollection')->willReturn($invoiceCollection);
        $orderMock->expects($this->once())->method('getCreditmemosCollection')->willReturn($creditmemoCollection);
        $orderMock->expects($this->once())->method('getIncrementId')->willReturn($id);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder', 'getCreditmemo'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('getCreditmemo')->willReturn($creditMemo);

        $instance = $this->getInstance();
        $result = $this->invokeArgs('getPartialId', [$paymentMock], $instance);

        $this->assertEquals($expected, $result);
    }
}
