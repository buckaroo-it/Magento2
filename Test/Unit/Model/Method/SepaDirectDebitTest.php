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
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as TransactionOrder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\SepaDirectDebit;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters;
use Zend\Validator\Iban;

class SepaDirectDebitTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = SepaDirectDebit::class;

    /**
     * Create a mock of the info instance with some defaults.
     *
     * @param string $country
     * @param string $class
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function getInfoInstanceMock($country = 'NL', $class = 'Magento\Payment\Model\Info')
    {
        $getMethodMock = 'getOrder';
        if ($class == 'Magento\Payment\Model\Info') {
            $getMethodMock = 'getQuote';
        }

        $infoInstanceMock = $this->getFakeMock($class)
            ->setMethods([$getMethodMock, 'getBillingAddress', 'getCountryId', 'getAdditionalInformation'])
            ->getMock();
        $infoInstanceMock->method($getMethodMock)->willReturnSelf();
        $infoInstanceMock->method('getBillingAddress')->willReturnSelf();
        $infoInstanceMock->method('getCountryId')->willReturn($country);

        return $infoInstanceMock;
    }

    /**
     * Test the assignData method.
     */
    public function testAssignData()
    {
        $data = $this->getObject(DataObject::class);
        $data->setBuckarooSkipValidation(0);
        $data->setAdditionalData([
            'buckaroo_skip_validation' => 1,
            'customer_bic' => 'NL32INGB',
            'customer_iban' => '0000012345',
            'customer_account_name' => 'TIG TEST'
        ]);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['setAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInstanceMock->expects($this->exactly(5))->method('setAdditionalInformation')->withConsecutive(
            ['buckaroo_skip_validation', 0],
            ['buckaroo_skip_validation', 1],
            ['customer_bic', 'NL32INGB'],
            ['customer_iban', '0000012345'],
            ['customer_account_name', 'TIG TEST']
        );

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($data);
        $this->assertInstanceOf(SepaDirectDebit::class, $result);
    }

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'customer_account_name' => 'firstname',
            'customer_iban'         => 'ibanio',
            'customer_bic'          => 'biccc',
            'order'                 => 'orderrr!',
        ];

        $payment = $this->getFakeMock(Payment::class)->setMethods(['getOrder', 'setAdditionalInformation'])->getMock();
        $payment->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $payment->expects($this->exactly(2))
            ->method('setAdditionalInformation')
            ->withConsecutive(['skip_push', 1], ['skip_push', 2]);

        $orderMock = $this->getFakeMock(TransactionOrder::class)
            ->setMethods(['setOrder', 'setMethod', 'setServices'])
            ->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals('sepadirectdebit', $services[0]['Name']);
                $this->assertEquals($fixture['customer_bic'], $services[0]['RequestParameter'][2]['_']);
                $this->assertEquals($fixture['customer_iban'], $services[0]['RequestParameter'][1]['_']);
                $this->assertEquals($fixture['customer_account_name'], $services[0]['RequestParameter'][0]['_']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['getAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInterface->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['customer_account_name'], ['customer_iban'], ['customer_bic'], ['customer_bic'])
            ->willReturnOnConsecutiveCalls(
                $fixture['customer_account_name'],
                $fixture['customer_iban'],
                $fixture['customer_bic'],
                $fixture['customer_bic']
            );

        $serviceParametersMock = $this->getFakeMock(ServiceParameters::class)
            ->setMethods(['getCreateCombinedInvoice'])
            ->getMock();
        $serviceParametersMock->expects($this->once())
            ->method('getCreateCombinedInvoice')
            ->with($payment, 'sepadirectdebit')
            ->willReturn(['invoiceData']);

        $instance = $this->getInstance([
            'transactionBuilderFactory' => $trxFactoryMock,
            'serviceParameters' => $serviceParametersMock
        ]);
        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getOrderTransactionBuilder($payment));
    }

    public function testGetSepaService()
    {
        $infoInstanceMock = $this->getFakeMock(Payment::class)->setMethods(['getAdditionalInformation'])->getMock();
        $infoInstanceMock->expects($this->exactly(4))->method('getAdditionalInformation')->willReturn('abc');

        $instance = $this->getInstance();
        $instance->setInfoInstance($infoInstanceMock);

        $result = $this->invoke('getSepaService', $instance);

        $this->assertInternalType('array', $result);
        $this->assertEquals('sepadirectdebit', $result['Name']);
        $this->assertEquals('Pay', $result['Action']);
        $this->assertEquals(1, $result['Version']);
        $this->assertCount(3, $result['RequestParameter']);

        $possibleParameters = ['customeraccountname', 'CustomerIBAN', 'CustomerBIC'];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertContains($array['Name'], $possibleParameters);
        }
    }

    /**
     * @return array
     */
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

        $this->assertInstanceOf(SepaDirectDebit::class, $result);
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
        $instance = $this->getInstance();
        $this->assertFalse($instance->getCaptureTransactionBuilder(''));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        $instance = $this->getInstance();
        $this->assertFalse($instance->getAuthorizeTransactionBuilder(''));
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

    /**
     * Test the getRefundTransactionBuilder method.
     */
    public function testGetRefundTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn('orderr');
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(SepaDirectDebit::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn('getAdditionalInformation');

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setMethod', 'setChannel', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setOrder')->with('orderr')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setChannel')->with('CallCenter')->willReturnSelf();
        $trxFactoryMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->willReturnSelf();
        $trxFactoryMock->expects($this->once())
            ->method('setServices')
            ->with(['Name' => 'sepadirectdebit', 'Action' => 'Refund', 'Version' => 1])
            ->willReturnSelf();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);

        $this->assertEquals($trxFactoryMock, $instance->getRefundTransactionBuilder($paymentMock));
    }

    /**
     * Test the happy path.
     *
     * @throws \Magento\Framework\Validator\Exception
     */
    public function testValidateHappyPath()
    {
        $iban = 'NL91ABNA0417164300';

        $infoInstanceMock = $this->getInfoInstanceMock();
        $infoInstanceMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'], ['customer_iban'], ['customer_account_name'])
            ->willReturnOnConsecutiveCalls(false, null, $iban, 'first name');

        $ibanValidator = $this->getFakeMock(Iban::class)->setMethods(['isValid'])->getMock();
        $ibanValidator->expects($this->once())->method('isValid')->with($iban)->willReturn(true);

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['create'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('create')->with(Iban::class)->willReturn($ibanValidator);

        $instance = $this->getInstance(['objectManager' => $objectManagerMock]);
        $instance->setData('info_instance', $infoInstanceMock);

        $this->assertEquals($instance, $instance->validate());
    }

    /**
     * Test the validation with an invalid account name.
     */
    public function testValidateInvalidAccountName()
    {
        $infoInstanceMock = $this->getInfoInstanceMock();
        $infoInstanceMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'], ['customer_iban'], ['customer_account_name'])
            ->willReturnOnConsecutiveCalls(false, null, null, 'first');

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        try {
            $instance->validate();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertEquals('Please enter a valid bank account holder name', $e->getMessage());
        }
    }

    /**
     * Test the path with an invalid IBAN.
     */
    public function testValidateInvalidIban()
    {
        $iban = 'wrong';

        $infoInstanceMock = $this->getInfoInstanceMock('BE');
        $infoInstanceMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'], ['customer_iban'], ['customer_account_name'])
            ->willReturnOnConsecutiveCalls(false, null, $iban, 'first name');

        $ibanValidator = $this->getFakeMock(Iban::class)->setMethods(['isValid'])->getMock();
        $ibanValidator->expects($this->once())->method('isValid')->with($iban)->willReturn(false);

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['create'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('create')->with(Iban::class)->willReturn($ibanValidator);

        $instance = $this->getInstance(['objectManager' => $objectManagerMock]);
        $instance->setData('info_instance', $infoInstanceMock);

        try {
            $instance->validate();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertEquals('Please enter a valid bank account number', $e->getMessage());
        }
    }

    /**
     * Test the path with a non-NL account and an non-valid BIC number.
     */
    public function testValidateInvalidBic()
    {
        $iban = 'wrong';

        $infoInstanceMock = $this->getInfoInstanceMock('BE');
        $infoInstanceMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'], ['customer_iban'], ['customer_account_name'])
            ->willReturnOnConsecutiveCalls(false, null, $iban, 'first name');

        $ibanValidator = $this->getFakeMock(Iban::class)->setMethods(['isValid'])->getMock();
        $ibanValidator->expects($this->once())->method('isValid')->with($iban)->willReturn(true);

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['create'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('create')->with(Iban::class)->willReturn($ibanValidator);

        $instance = $this->getInstance(['objectManager' => $objectManagerMock]);
        $instance->setData('info_instance', $infoInstanceMock);

        try {
            $instance->validate();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertEquals('Please enter a valid BIC number', $e->getMessage());
        }
    }

    /**
     * Test the path with a Payment instance instead of the quote instance.
     *
     * @throws \Magento\Framework\Validator\Exception
     */
    public function testValidatePaymentInstance()
    {
        $iban = 'NL91ABNA0417164300';

        $infoInstanceMock = $this->getInfoInstanceMock('NL', 'Magento\Sales\Model\Order\Payment');
        $infoInstanceMock->expects($this->exactly(4))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'], ['customer_iban'], ['customer_account_name'])
            ->willReturnOnConsecutiveCalls(false, null, $iban, 'first name');

        $ibanValidator = $this->getFakeMock(Iban::class)->setMethods(['isValid'])->getMock();
        $ibanValidator->expects($this->once())->method('isValid')->with($iban)->willReturn(true);

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['create'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('create')->with(Iban::class)->willReturn($ibanValidator);

        $instance = $this->getInstance(['objectManager' => $objectManagerMock]);
        $instance->setData('info_instance', $infoInstanceMock);

        $this->assertEquals($instance, $instance->validate());
    }

    /**
     * Test what happens when the validation is skipped.
     */
    public function testValidateSkip()
    {
        $infoInstanceMock = $this->getInfoInstanceMock('NL', 'Magento\Sales\Model\Order\Payment');
        $infoInstanceMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_skip_validation')
            ->willReturn(true);

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $this->assertEquals($instance, $instance->validate());
    }
}
