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

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as orderTrxBuilder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Kbc;

class KbcTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Kbc::class;

    public function testGetOrderTransactionBuilder()
    {
        $expectedServices = ['Name' => 'KBCPaymentButton', 'Action' => 'Pay', 'Version' => 1];

        $orderMock = $this->getFakeMock(Order::class, true);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $orderTrxMock = $this->getFakeMock(orderTrxBuilder::class)
            ->setMethods(['setOrder', 'setServices', 'setMethod'])
            ->getMock();
        $orderTrxMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setServices')->with($expectedServices)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();

        $transactionFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderTrxMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionFactoryMock]);
        $result = $instance->getOrderTransactionBuilder($paymentMock);

        $this->assertEquals($orderTrxMock, $result);
    }

    public function testGetCaptureTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();

        $result = $instance->getCaptureTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testGetAuthorizeTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();

        $result = $instance->getAuthorizeTransactionBuilder($paymentMock);
        $this->assertFalse($result);
    }

    public function testGetRefundTransactionBuilder()
    {
        $expectedServices = ['Name' => 'KBCPaymentButton', 'Action' => 'Refund', 'Version' => 1];

        $orderMock = $this->getFakeMock(Order::class, true);

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(Kbc::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            ->willReturn('abc123');

        $orderTrxMock = $this->getFakeMock(orderTrxBuilder::class)
            ->setMethods(['setOrder', 'setServices', 'setMethod', 'setOriginalTransactionKey'])
            ->getMock();
        $orderTrxMock->expects($this->once())->method('setOrder')->with($orderMock)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setServices')->with($expectedServices)->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderTrxMock->expects($this->once())->method('setOriginalTransactionKey')->with('abc123')->willReturnSelf();

        $transactionFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionFactoryMock->expects($this->once())->method('get')->with('refund')->willReturn($orderTrxMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionFactoryMock]);
        $result = $instance->getRefundTransactionBuilder($paymentMock);

        $this->assertEquals($orderTrxMock, $result);
    }

    public function testGetVoidTransactionBuilder()
    {
        $paymentMock = $this->getFakeMock(Payment::class, true);
        $instance = $this->getInstance();

        $result = $instance->getVoidTransactionBuilder($paymentMock);
        $this->assertTrue($result);
    }
}
