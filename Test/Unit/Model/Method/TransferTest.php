<?php

/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\Method;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Transfer;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters;

class TransferTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = \TIG\Buckaroo\Model\Method\Transfer::class;

    /**
     * @var \TIG\Buckaroo\Model\Method\Transfer
     */
    protected $object;

    /**
     * @var TransactionBuilderFactory|\Mockery\MockInterface
     */
    protected $transactionBuilderFactory;

    /**
     * @var \Magento\Framework\ObjectManagerInterface|\Mockery\MockInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Payment\Model\InfoInterface|\Magento\Sales\Api\Data\OrderPaymentInterface|\Mockery\MockInterface
     */
    protected $paymentInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|\Mockery\MockInterface
     */
    protected $scopeConfig;

    /**
     * @var
     */
    protected $configProviderMethodFactory;

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->objectManager               = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->transactionBuilderFactory   = \Mockery::mock(
            TransactionBuilderFactory::class
        );
        $this->scopeConfig                 = \Mockery::mock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->configProviderMethodFactory = \Mockery::mock(Factory::class);

        $this->object = $this->objectManagerHelper->getObject(
            \TIG\Buckaroo\Model\Method\Transfer::class,
            [
                'scopeConfig'                 => $this->scopeConfig,
                'objectManager'               => $this->objectManager,
                'transactionBuilderFactory'   => $this->transactionBuilderFactory,
                'configProviderMethodFactory' => $this->configProviderMethodFactory
            ]
        );

        $this->paymentInterface = \Mockery::mock(
            \Magento\Payment\Model\InfoInterface::class,
            \Magento\Sales\Api\Data\OrderPaymentInterface::class
        );
    }

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

        $order = \Mockery::mock(\TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order::class);
        $order->shouldReceive('getCustomerEmail')->andReturn($fixture['email']);
        $order->shouldReceive('setOrder')->with($order)->andReturnSelf();
        $order->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();

        $billingAddress = \Mockery::mock(Address::class);
        $billingAddress->shouldReceive('getFirstname')->andReturn($fixture['firstname']);
        $billingAddress->shouldReceive('getLastname')->andReturn($fixture['lastname']);
        $billingAddress->shouldReceive('getCountryId')->andReturn($fixture['country']);
        $order->shouldReceive('getBillingAddress')->andReturn($billingAddress);

        $order->shouldReceive('setServices')->andReturnUsing(
            function ($services) use ($fixture, $order) {
                $this->assertEquals($fixture['firstname'], $services[0]['RequestParameter'][0]['_']);
                $this->assertEquals($fixture['lastname'], $services[0]['RequestParameter'][1]['_']);
                $this->assertEquals($fixture['country'], $services[0]['RequestParameter'][2]['_']);
                $this->assertEquals($fixture['email'], $services[0]['RequestParameter'][3]['_']);

                $this->assertEquals('transfer', $services[0]['Name']);
                $this->assertEquals('Pay', $services[0]['Action']);

                return $order;
            }
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->configProviderMethodFactory->shouldReceive('get')->once()->with('transfer')->andReturnSelf();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->configProviderMethodFactory->shouldReceive('getDueDate')->once()->andReturn('7');
        $this->configProviderMethodFactory->shouldReceive('getSendEmail')->once()->andReturn('true');

        $this->paymentInterface->shouldReceive('getOrder')->andReturn($order);
        $this->paymentInterface->shouldReceive('setAdditionalInformation')->withArgs(['skip_push', 1]);
        $this->transactionBuilderFactory->shouldReceive('get')->with('order')->andReturn($order);

        $infoInterface = \Mockery::mock(\Magento\Payment\Model\InfoInterface::class)->makePartial();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->object->setData('info_instance', $infoInterface);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertEquals($order, $this->object->getOrderTransactionBuilder($this->paymentInterface));
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

    /**
     * Test the getCaptureTransactionBuilder method.
     */
    public function testGetCaptureTransactionBuilder()
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertFalse($this->object->getCaptureTransactionBuilder($this->paymentInterface));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertFalse($this->object->getAuthorizeTransactionBuilder($this->paymentInterface));
    }

    /**
     * Test the getRefundTransactionBuilder method.
     */
    public function testGetRefundTransactionBuilder()
    {
        $fixture = [
            'order' => 'orderrr!',
        ];

        $this->paymentInterface->shouldReceive('getOrder')->andReturn($fixture['order']);
        $this->paymentInterface->shouldReceive('getAdditionalInformation')->with(
            'buckaroo_transaction_key'
        )->andReturn('getAdditionalInformation');
        $this->paymentInterface->shouldReceive('getAdditionalInformation')->with(
            'buckaroo_original_transaction_key'
        )->andReturn('getAdditionalInformation');

        $this->transactionBuilderFactory->shouldReceive('get')->with('refund')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOrder')->with($fixture['order'])->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setServices')->andReturnUsing(
            function ($services) {
                $services['Name']   = 'sofortbanking';
                $services['Action'] = 'Refund';

                return $this->transactionBuilderFactory;
            }
        );
        $this->transactionBuilderFactory->shouldReceive('setMethod')->with('TransactionRequest')->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->andReturnSelf();
        $this->transactionBuilderFactory->shouldReceive('setChannel')->with('CallCenter')->andReturnSelf();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->assertEquals(
            $this->transactionBuilderFactory,
            $this->object->getRefundTransactionBuilder($this->paymentInterface)
        );
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
