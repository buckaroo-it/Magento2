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
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Refund;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\IdealProcessing;
use TIG\Buckaroo\Test\BaseTest;

class IdealProcessingTest extends BaseTest
{
    protected $instanceClass = IdealProcessing::class;

    /**
     * @return array
     */
    public function assignDataProvider()
    {
        return [
            'no data' => [
                []
            ],
            'with skip validation data' => [
                [
                    'additional_data' => [
                        'buckaroo_skip_validation' => '1',
                    ]
                ]
            ],
            'with form data' => [
                [
                    'additional_data' => [
                        'issuer' => 'ING',
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
        $this->assertInstanceOf(IdealProcessing::class, $result);
    }

    public function testGetOrderTransactionBuilder()
    {
        $orderTransactionMock = $this->getFakeMock(Order::class)
            ->setMethods(['setOrder', 'serServices', 'setMethod'])
            ->getMock();
        $orderTransactionMock->expects($this->once())->method('setOrder')->willReturnSelf();
        $orderTransactionMock->expects($this->once())->method('setMethod')->willReturnSelf();

        $transactionBuilderMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get'])
            ->getMock();
        $transactionBuilderMock->expects($this->once())
            ->method('get')
            ->with('order')
            ->willReturn($orderTransactionMock);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())->method('getOrder');

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionBuilderMock]);
        $result = $instance->getOrderTransactionBuilder($paymentMock);

        $this->assertInstanceOf(Order::class, $result);

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('idealprocessing', $services['Name']);
        $this->assertEquals('Pay', $services['Action']);
        $this->assertEquals(2, $services['Version']);
        $this->assertCount(1, $services['RequestParameter']);

        $possibleParameters = ['issuer'];

        foreach ($services['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertContains($array['Name'], $possibleParameters);
        }
    }

    public function testGetCaptureTransactionBuilder()
    {
        $paymentMock = $this->getMockBuilder(OrderPaymentInterface::class)->getMockForAbstractClass();
        $instance = $this->getInstance();

        $result = $instance->getCaptureTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $paymentMock = $this->getMockBuilder(OrderPaymentInterface::class)->getMockForAbstractClass();
        $instance = $this->getInstance();

        $result = $instance->getAuthorizeTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testGetRefundTransactionBuilder()
    {
        $orderTransactionMock = $this->getFakeMock(Refund::class)
            ->setMethods(['setOrder', 'serServices', 'setMethod'])
            ->getMock();
        $orderTransactionMock->expects($this->once())->method('setOrder')->willReturnSelf();
        $orderTransactionMock->expects($this->once())->method('setMethod')->willReturnSelf();

        $transactionBuilderMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get'])
            ->getMock();
        $transactionBuilderMock->expects($this->once())
            ->method('get')
            ->with('refund')
            ->willReturn($orderTransactionMock);

        $paymentMock = $this->getFakeMock(OrderPaymentInterface::class)
            ->setMethods(['getOrder'])
            ->getMockForAbstractClass();
        $paymentMock->expects($this->once())->method('getOrder');

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionBuilderMock]);
        $result = $instance->getRefundTransactionBuilder($paymentMock);

        $this->assertInstanceOf(Refund::class, $result);

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('idealprocessing', $services['Name']);
        $this->assertEquals('Refund', $services['Action']);
        $this->assertEquals(1, $services['Version']);
    }

    public function testGetVoidTransactionBuilder()
    {
        $paymentMock = $this->getMockBuilder(OrderPaymentInterface::class)->getMockForAbstractClass();
        $instance = $this->getInstance();

        $result = $instance->getVoidTransactionBuilder($paymentMock);
        $this->assertTrue($result);
    }
}
