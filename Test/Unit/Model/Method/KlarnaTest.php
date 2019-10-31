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

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as orderTrxBuilder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Klarna;
use TIG\Buckaroo\Service\Software\Data;
use TIG\Buckaroo\Test\BaseTest;

class KlarnaTest extends BaseTest
{
    protected $instanceClass = Klarna::class;

    /**
     * @return array
     */
    public function canCaptureProvider()
    {
        return [
            'can capture' => [
                'authorize',
                true
            ],
            'can not capture' => [
                'order',
                false
            ]
        ];
    }

    /**
     * @param $method
     * @param $expected
     *
     * @dataProvider canCaptureProvider
     */
    public function testCanCapture($method, $expected)
    {
        $scopeConfigMock = $this->getFakeMock(Config::class)->setMethods(['getValue'])->getMock();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn($method);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->canCapture();

        $this->assertEquals($expected, $result);
    }

    public function testGetOrderTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();

        $result = $instance->getOrderTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $addressMock = $this->getFakeMock(Address::class)->setMethods(['getCountryId'])->getMock();
        $addressMock->expects($this->exactly(6))->method('getCountryId')->willReturn('NL');

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['getBillingAddress', 'getShippingAddress', 'getItems'])
            ->getMock();
        $orderMock->expects($this->exactly(2))->method('getBillingAddress')->willReturn($addressMock);
        $orderMock->expects($this->exactly(2))->method('getShippingAddress')->willReturn($addressMock);
        $orderMock->expects($this->once())->method('getItems')->willReturn([]);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->exactly(8))->method('getOrder')->willReturn($orderMock);

        $cartMock = $this->getFakeMock(Cart::class)->setMethods(['getItems'])->getMock();
        $cartMock->expects($this->once())->method('getItems')->willReturn([]);

        $softwareData = $this->getFakeMock(Data::class)->setMethods(['getProductMetaData', 'getEdition'])->getMock();
        $softwareData->expects($this->once())->method('getProductMetaData')->willReturnSelf();
        $softwareData->expects($this->once())->method('getEdition')->willReturn('Community');

        $orderTrxMock = $this->getFakeMock(orderTrxBuilder::class)
            ->setMethods(['setOrder', 'setServices', 'setMethod'])
            ->getMock();
        $orderTrxMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setServices')->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setMethod')->with('DataRequest')->willReturnSelf();

        $transactionFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderTrxMock);

        $instance = $this->getInstance([
            'cart' => $cartMock,
            'softwareData' => $softwareData,
            'transactionBuilderFactory' => $transactionFactoryMock
        ]);
        $result = $instance->getAuthorizeTransactionBuilder($paymentMock);

        $this->assertEquals($orderTrxMock, $result);
    }

    public function testGetVoidTransactionBuilder()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->exactly(2))->method('getOrder')->willReturn($orderMock);

        $orderTrxMock = $this->getFakeMock(orderTrxBuilder::class)
            ->setMethods(['setOrder', 'setServices', 'setMethod'])
            ->getMock();
        $orderTrxMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setServices')->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setMethod')->with('DataRequest')->willReturnSelf();

        $transactionFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderTrxMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionFactoryMock]);
        $result = $instance->getVoidTransactionBuilder($paymentMock);

        $this->assertEquals($orderTrxMock, $result);
    }

    /**
     * @return array
     */
    public function processCustomPostDataProvider()
    {
        return [
            'no existing reservation number' => [
                null,
                'TIG1234'
            ],
            'existing reservation number' => [
                '6543TIG',
                '6543TIG'
            ]
        ];
    }

    /**
     * @param $reservationNumber
     * @param $expected
     *
     * @dataProvider processCustomPostDataProvider
     */
    public function testProcessCustomPostData($reservationNumber, $expected)
    {
        $postDataMock = (Object)[
            'Services' => (Object)[
                'Service' => (Object)[
                    'ResponseParameter' => (Object)[
                        '_' => 'TIG1234'
                    ]
                ]
            ]
        ];

        $saveCalled = 'once';
        if ($reservationNumber) {
            $saveCalled = 'never';
        }

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['save'])->getMock();
        $orderMock->expects($this->$saveCalled())->method('save');
        $orderMock->setBuckarooReservationNumber($reservationNumber);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $instance->processCustomPostData($paymentMock, $postDataMock);

        $this->assertEquals($expected, $orderMock->getBuckarooReservationNumber());
    }

    /**
     * @return array
     */
    public function assignDataProvider()
    {
        return [
            'no data' => [
                [],
                [
                    'buckaroo_skip_validation' => null,
                ]
            ],
            'with skip validation data' => [
                [
                    'additional_data' => [
                        'buckaroo_skip_validation' => '1',
                    ]
                ],
                [
                    'buckaroo_skip_validation' => '1'
                ]
            ],
            'with form data' => [
                [
                    'additional_data' => [
                        'termsCondition'       => true,
                        'customer_gender'      => 'male',
                        'customer_billingName' => 'TIG',
                        'customer_iban'        => 'INGBNL2A',
                        'customer_DoB'         => '10/07/1970',
                        'selectedBusiness'     => '2',
                        'COCNumber'            => '123456',
                        'CompanyName'          => 'TIG',
                        'customer_telephone'   => '0201122233',
                    ]
                ],
                [
                    'buckaroo_skip_validation' => null,
                    'termsCondition'       => true,
                    'customer_gender'      => 'male',
                    'customer_billingName' => 'TIG',
                    'customer_iban'        => 'INGBNL2A',
                    'customer_DoB'         => '10-07-1970',
                    'selectedBusiness'     => '2',
                    'COCNumber'            => '123456',
                    'CompanyName'          => 'TIG',
                    'customer_telephone'   => '0201122233',
                ]
            ],
        ];
    }

    /**
     * @param $data
     * @param $expected
     * @throws \Exception
     *
     * @dataProvider assignDataProvider
     */
    public function testAssignData($data, $expected)
    {
        $dataObject = $this->getObject(DataObject::class);
        $dataObject->addData($data);

        $instance = $this->getInstance();

        $infoInstanceMock = $this->getFakeMock(QuotePayment::class)->setMethods(null)->getMock();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($dataObject);
        $this->assertInstanceOf(Klarna::class, $result);

        $tst = $infoInstanceMock->getAdditionalInformation();
        $this->assertEquals($expected, $tst);
    }

    /**
     * @return array
     */
    public function calculateProductPriceProvider()
    {
        return [
            'including tax' => [
                1.23,
                2.34,
                true,
                1.23
            ],
            'excluding tax' => [
                3.45,
                4.56,
                false,
                4.56
            ]
        ];
    }

    /**
     * @param $priceInclTax
     * @param $priceExclTax
     * @param $includesTax
     * @param $expected
     *
     * @dataProvider calculateProductPriceProvider
     */
    public function testCalculateProductPrice($priceInclTax, $priceExclTax, $includesTax, $expected)
    {
        $itemMock = $this->getFakeMock(Item::class)->setMethods(['getRowTotalInclTax', 'getRowTotal'])->getMock();
        $itemMock->expects($this->exactly((int)$includesTax))->method('getRowTotalInclTax')->willReturn($priceInclTax);
        $itemMock->expects($this->exactly((int)(!$includesTax)))->method('getRowTotal')->willReturn($priceExclTax);

        $instance = $this->getInstance();
        $result = $instance->calculateProductPrice($itemMock, $includesTax);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getTaxPercentProvider()
    {
        return [
            'no tax' => [
                0,
                0,
                0
            ],
            'tax from data' => [
                5.67,
                6.78,
                5.67
            ],
            'tax from order item' => [
                0,
                7.89,
                7.89
            ]
        ];
    }

    /**
     * @param $dataTax
     * @param $itemTax
     * @param $expected
     * @throws \Exception
     *
     * @dataProvider getTaxPercentProvider
     */
    public function testGetTaxPercent($dataTax, $itemTax, $expected)
    {
        $expectedCall = (((bool)$dataTax) ? 'never' : 'once');

        $orderItemMock = $this->getFakeMock(Item::class)->setMethods(['getTaxPercent'])->getMock();
        $orderItemMock->expects($this->$expectedCall())->method('getTaxPercent')->willReturn($itemTax);

        $dataObject = $this->getObject(DataObject::class);
        $dataObject->setTaxPercent($dataTax);
        $dataObject->setOrderItem($orderItemMock);

        $instance = $this->getInstance();
        $result = $this->invokeArgs('getTaxPercent', [$dataObject], $instance);
        $this->assertEquals($expected, $result);
    }
}
