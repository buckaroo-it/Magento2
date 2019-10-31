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

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as TransactionOrder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Transfer;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters;

class TransferTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = \TIG\Buckaroo\Model\Method\Transfer::class;

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'firstname' => 'John',
            'lastname'  => 'Doe',
            'country'   => 'NL',
            'email'     => 'john@doe.com',
        ];

        $billingAddress = $this->getFakeMock(Address::class)
            ->setMethods(['getFirstname', 'getLastname', 'getCountryId'])
            ->getMock();
        $billingAddress->expects($this->once())->method('getFirstname')->willReturn($fixture['firstname']);
        $billingAddress->expects($this->once())->method('getLastname')->willReturn($fixture['lastname']);
        $billingAddress->expects($this->once())->method('getCountryId')->willReturn($fixture['country']);


        $orderMock = $this->getFakeMock(TransactionOrder::class)
            ->setMethods(['getCustomerEmail', 'setOrder', 'setMethod', 'getBillingAddress', 'setServices'])
            ->getMock();
        $orderMock->expects($this->once())->method('getCustomerEmail')->willReturn($fixture['email']);
        $orderMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddress);
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['firstname'], $services[0]['RequestParameter'][0]['_']);
                $this->assertEquals($fixture['lastname'], $services[0]['RequestParameter'][1]['_']);
                $this->assertEquals($fixture['country'], $services[0]['RequestParameter'][2]['_']);
                $this->assertEquals($fixture['email'], $services[0]['RequestParameter'][3]['_']);

                $this->assertEquals('transfer', $services[0]['Name']);
                $this->assertEquals('Pay', $services[0]['Action']);

                return $orderMock;
            }
        );

        $configFactoryMock = $this->getFakeMock(Factory::class)
            ->setMethods(['get', 'getDueDate', 'getSendEmail'])
            ->getMock();
        $configFactoryMock->expects($this->once())->method('get')->with('transfer')->willReturnSelf();
        $configFactoryMock->expects($this->once())->method('getDueDate')->willReturn('7');
        $configFactoryMock->expects($this->once())->method('getSendEmail')->willReturn('true');

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'setAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->exactly(3))->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->withConsecutive(['skip_push', 1], ['skip_push', 2]);

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $serviceParametersMock = $this->getFakeMock(ServiceParameters::class)
            ->setMethods(['getCreateCombinedInvoice'])
            ->getMock();
        $serviceParametersMock->expects($this->once())
            ->method('getCreateCombinedInvoice')
            ->with($paymentMock, 'transfer')
            ->willReturn(['invoiceData']);

        $instance = $this->getInstance([
            'configProviderMethodFactory' => $configFactoryMock,
            'transactionBuilderFactory' => $trxFactoryMock,
            'serviceParameters' => $serviceParametersMock
        ]);

        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getOrderTransactionBuilder($paymentMock));
    }

    public function testGetTransferService()
    {
        $TransferConfigMock = $this->getFakeMock(Transfer::class)->setMethods(['getDueDate', 'getSendEmail'])->getMock();
        $TransferConfigMock->expects($this->once())->method('getDueDate');
        $TransferConfigMock->expects($this->once())->method('getSendEmail');

        $factoryMock = $this->getFakeMock(Factory::class)->setMethods(['get'])->getMock();
        $factoryMock->expects($this->once())->method('get')->with('transfer')->willReturn($TransferConfigMock);

        $addressMock = $this->getFakeMock(Address::class, true);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['getBillingAddress'])->getMock();
        $orderMock->expects($this->once())->method('getBillingAddress')->willReturn($addressMock);

        $infoInstanceMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $infoInstanceMock->expects($this->exactly(2))->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance(['configProviderMethodFactory' => $factoryMock]);
        $result = $this->invokeArgs('getTransferService', [$infoInstanceMock], $instance);

        $this->assertInternalType('array', $result);
        $this->assertEquals('transfer', $result['Name']);
        $this->assertEquals('Pay', $result['Action']);
        $this->assertEquals(2, $result['Version']);
        $this->assertCount(6, $result['RequestParameter']);

        $possibleParameters = ['CustomerEmail', 'CustomerFirstName', 'CustomerLastName', 'CustomerCountry', 'DateDue', 'SendMail'];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertContains($array['Name'], $possibleParameters);
        }
    }

    public function afterOrderProvider()
    {
        return [
            'no service' => [
                [],
                null
            ],
            'no invoicekey' => [
                [
                    (Object)[
                        'Name' => 'CreditManagement3',
                        'ResponseParameter' => (Object)[
                            'Name' => 'ResponseName',
                            '_' => 'abc'
                        ]
                    ]
                ],
                null
            ],
            'incorrect service' => [
                [
                    (Object)[
                        'Name' => 'PayPerEmail',
                        'ResponseParameter' => (Object)[
                            'Name' => 'InvoiceKey',
                            '_' => 'def'
                        ]
                    ]
                ],
                null
            ],
            'has invoicekey' => [
                [
                    (Object)[
                        'Name' => 'CreditManagement3',
                        'ResponseParameter' => (Object)[
                            'Name' => 'InvoiceKey',
                            '_' => 'ghi'
                        ]
                    ]
                ],
                'ghi'
            ],
        ];
    }

    /**
     * @param $service
     * @param $expected
     *
     * @dataProvider afterOrderProvider
     */
    public function testAfterOrder($service, $expected)
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->setMethods(null)->getMock();

        $respone = [
            0 => (Object)[
                'Services' => (Object)[
                    'Service' => $service
                ]
            ]
        ];

        $instance = $this->getInstance();
        $result = $this->invokeArgs('afterOrder', [$infoInstanceMock, $respone], $instance);

        $this->assertInstanceOf(\TIG\Buckaroo\Model\Method\Transfer::class, $result);
        $this->assertEquals($expected, $infoInstanceMock->getAdditionalInformation('buckaroo_cm3_invoice_key'));
    }

    public function getCM3InvoiceKeyProvider()
    {
        return [
            'object, has invoiceKey' => [
                (Object)[
                    'Name' => 'InvoiceKey',
                    '_' => 'key123'
                ],
                'key123'
            ],
            'object, no invoiceKey' => [
                (Object)[
                    'Name' => 'Debtor',
                    '_' => 'TIG'
                ],
                ''
            ],
            'array with one item, has invoiceKey' => [
                [
                    (Object)[
                        'Name' => 'InvoiceKey',
                        '_' => 'invoice456'
                    ]
                ],
                'invoice456'
            ],
            'array with one item, no invoiceKey' => [
                [
                    (Object)[
                        'Name' => 'Debtor',
                        '_' => 'TIG'
                    ]
                ],
                ''
            ],
            'array with multiple items, has invoiceKey' => [
                [
                    (Object)[
                        'Name' => 'Status',
                        '_' => 'Paid'
                    ],
                    (Object)[
                        'Name' => 'InvoiceKey',
                        '_' => 'order789'
                    ],
                    (Object)[
                        'Name' => 'Debtor',
                        '_' => 'TIG'
                    ],
                ],
                'order789'
            ],
            'array with multiple items, no invoiceKey' => [
                [
                    (Object)[
                        'Name' => 'Status',
                        '_' => 'Paid'
                    ],
                    (Object)[
                        'Name' => 'Debtor',
                        '_' => 'TIG'
                    ],
                ],
                ''
            ],
        ];
    }

    /**
     * @param $responeParameterData
     * @param $expected
     *
     * @dataProvider getCM3InvoiceKeyProvider
     */
    public function testGetCM3InvoiceKey($responeParameterData, $expected)
    {
        $instance = $this->getInstance();
        $result = $this->invokeArgs('getCM3InvoiceKey', [$responeParameterData], $instance);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test the getCaptureTransactionBuilder method.
     */
    public function testGetCaptureTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();
        $this->assertFalse($instance->getCaptureTransactionBuilder($paymentMock));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();
        $this->assertFalse($instance->getAuthorizeTransactionBuilder($paymentMock));
    }

    /**
     * Test the getRefundTransactionBuilder method.
     */
    public function testGetRefundTransactionBuilder()
    {
        $fixture = [
            'order' => 'orderrr!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_original_transaction_key')
            ->willReturn('getAdditionalInformation');

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setMethod', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $trxFactoryMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($trxFactoryMock) {
                $services['Name']   = 'sofortbanking';
                $services['Action'] = 'Refund';

                return $trxFactoryMock;
            }
        );

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $this->assertEquals($trxFactoryMock, $instance->getRefundTransactionBuilder($paymentMock));
    }

    /**
     * Test the getVoidTransactionBuild method.
     */
    public function testGetVoidTransactionBuilder()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $infoInstanceMock->expects($this->exactly(2))->method('getOrder')->willReturn($orderMock);
        $infoInstanceMock->expects($this->once())->method('getAdditionalInformation')->willReturn('abc');

        $serviceParametersResult = ['Name' => 'CreditManagement', 'Action' => 'CreateCreditNote'];

        $serviceParametersMock = $this->getFakeMock(ServiceParameters::class)
            ->setMethods(['getCreateCreditNote'])
            ->getMock();
        $serviceParametersMock->expects($this->once())
            ->method('getCreateCreditNote')
            ->with($infoInstanceMock)
            ->willReturn($serviceParametersResult);

        $orderTransactionMock = $this->getFakeMock(Order::class)->setMethods(['setMethod'])->getMock();
        $orderTransactionMock->expects($this->once())->method('setMethod')->with('DataRequest')->willReturnSelf();

        $transactionBuilderMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionBuilderMock->expects($this->once())
            ->method('get')
            ->with('order')
            ->willReturn($orderTransactionMock);

        $instance = $this->getInstance([
            'serviceParameters' => $serviceParametersMock,
            'transactionBuilderFactory' => $transactionBuilderMock
        ]);

        $result = $instance->getVoidTransactionBuilder($infoInstanceMock);
        $this->assertInstanceOf(Order::class, $result);

        $services = $result->getServices();
        $this->assertEquals($serviceParametersResult, $services);
    }
}
