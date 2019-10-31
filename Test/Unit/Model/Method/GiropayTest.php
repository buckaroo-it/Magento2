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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Giropay;

class GiropayTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Giropay::class;

    /**
     * Test the assignData method.
     */
    public function testAssignData()
    {
        $data = $this->getObject(DataObject::class);
        $data->setBuckarooSkipValidation(0);
        $data->setAdditionalData([
            'customer_bic' => 'NL32INGB'
        ]);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['setAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInstanceMock->expects($this->exactly(2))->method('setAdditionalInformation')->withConsecutive(
            ['buckaroo_skip_validation', 0],
            ['customer_bic', 'NL32INGB']
        );

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($data);
        $this->assertInstanceOf(Giropay::class, $result);
    }

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'customer_bic' => 'biccib',
            'order' => 'orderrr!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('customer_bic')
            ->willReturn($fixture['customer_bic']);

        $orderMock =$this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setServices'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals('giropay', $services['Name']);
                $this->assertEquals($fixture['customer_bic'], $services['RequestParameter'][0]['_']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getOrderTransactionBuilder($paymentMock));
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
            ->with(Giropay::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
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
        $trxFactoryMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($trxFactoryMock) {
                $services['Name'] = 'giropay';
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
        $instance = $this->getInstance();
        $this->assertTrue($instance->getVoidTransactionBuilder(''));
    }

    /**
     * Test the validation method happy path.
     */
    public function testValidate()
    {
        $paymentInfoMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['getQuote', 'getBillingAddress', 'getCountryId', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentInfoMock->expects($this->once())->method('getQuote')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getBillingAddress')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getCountryId')->willReturn(4);
        $paymentInfoMock->expects($this->exactly(2))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'])
            ->willReturnOnConsecutiveCalls(false, 'ABCDEF1E');

        $instance = $this->getInstance();
        $instance->setData('info_instance', $paymentInfoMock);
        $result = $instance->validate();

        $this->assertInstanceOf(Giropay::class, $result);
    }

    /**
     * Test the validation method happy path.
     */
    public function testValidateInvalidBic()
    {
        $paymentInfoMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['getQuote', 'getBillingAddress', 'getCountryId', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentInfoMock->expects($this->once())->method('getQuote')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getBillingAddress')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getCountryId')->willReturn(4);
        $paymentInfoMock->expects($this->exactly(2))
            ->method('getAdditionalInformation')
            ->withConsecutive(['buckaroo_skip_validation'], ['customer_bic'])
            ->willReturnOnConsecutiveCalls(false, 'wrong');

        $instance = $this->getInstance();
        $instance->setData('info_instance', $paymentInfoMock);

        try {
            $instance->validate();
        } catch (LocalizedException $e) {
            $this->assertEquals('Please enter a valid BIC number', $e->getMessage());
        }
    }

    /**
     * Test the validation method happy path.
     */
    public function testValidateSkipValidation()
    {
        $paymentInfoMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['getQuote', 'getBillingAddress', 'getCountryId', 'getAdditionalInformation'])
            ->getMockForAbstractClass();
        $paymentInfoMock->expects($this->once())->method('getQuote')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getBillingAddress')->willReturnSelf();
        $paymentInfoMock->expects($this->once())->method('getCountryId')->willReturn(4);
        $paymentInfoMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_skip_validation')
            ->willReturn(true);

        $instance = $this->getInstance();
        $instance->setData('info_instance', $paymentInfoMock);

        $result = $instance->validate();

        $this->assertInstanceOf(Giropay::class, $result);
    }
}
