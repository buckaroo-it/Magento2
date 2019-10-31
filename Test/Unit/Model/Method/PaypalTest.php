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
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Paypal;

class PaypalTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Paypal::class;

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'order' => 'orderrr!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setServices'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($orderMock) {
                $this->assertEquals('paypal', $services['Name']);
                $this->assertEquals('Pay', $services['Action']);

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
            'card_type' => 'fooname',
            'order' => 'orderrr!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(Paypal::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn('getAdditionalInformation');

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setMethod', 'setChannel', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setChannel')->with('CallCenter')->willReturnSelf();
        $trxFactoryMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($trxFactoryMock) {
                $services['Name'] = 'paypal';
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
}
