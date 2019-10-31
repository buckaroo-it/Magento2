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
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order as TransactionBuilderOrder;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Refund as TransactionBuilderRefund;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Payconiq;
use TIG\Buckaroo\Test\BaseTest;

class PayconiqTest extends BaseTest
{
    protected $instanceClass = Payconiq::class;

    public function testGetOrderTransactionBuilder()
    {
        $orderMock = $this->getFakeMock(Order::class, true);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $transactionOrderMock = $this->getFakeMock(TransactionBuilderOrder::class)->setMethods(null)->getMock();

        $transactionBuildMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $transactionBuildMock->expects($this->once())->method('get')->with('order')->willReturn($transactionOrderMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $transactionBuildMock]);
        $result = $instance->getOrderTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderOrder::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals($orderMock, $result->getOrder());
        $this->assertEquals('TransactionRequest', $result->getMethod());

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('payconiq', $services['Name']);
        $this->assertEquals('Pay', $services['Action']);
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
        $orderMock = $this->getFakeMock(Order::class, true);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $transactionRefundMock = $this->getFakeMock(TransactionBuilderRefund::class)->setMethods(null)->getMock();

        $trxBuildMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxBuildMock->expects($this->once())->method('get')->with('refund')->willReturn($transactionRefundMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxBuildMock]);
        $result = $instance->getRefundTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderRefund::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals($orderMock, $result->getOrder());
        $this->assertEquals('TransactionRequest', $result->getMethod());
        $this->assertEquals('CallCenter', $result->getChannel());

        $services = $result->getServices();
        $this->assertInternalType('array', $services);
        $this->assertEquals('payconiq', $services['Name']);
        $this->assertEquals('Refund', $services['Action']);
    }

    public function testGetVoidTransactionBuilder()
    {
        $transactionId = '123abc';
        $orderMock = $this->getFakeMock(Order::class, true);

        $paymentMock = $this->getFakeMock(Payment::class)->setMethods(['getOrder','getParentTransactionId'])->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $paymentMock->expects($this->once())->method('getParentTransactionId')->willReturn($transactionId);

        $transactionRefundMock = $this->getFakeMock(TransactionBuilderOrder::class)->setMethods(null)->getMock();

        $trxBuildMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxBuildMock->expects($this->once())->method('get')->with('order')->willReturn($transactionRefundMock);

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxBuildMock]);
        $result = $instance->getVoidTransactionBuilder($paymentMock);

        $this->assertInstanceOf(TransactionBuilderOrder::class, $result);
        $this->assertInstanceOf(Order::class, $result->getOrder());
        $this->assertEquals($orderMock, $result->getOrder());
        $this->assertEquals(0, $result->getAmount());
        $this->assertEquals('void', $result->getType());
        $this->assertEquals('CancelTransaction', $result->getMethod());
        $this->assertEquals($transactionId, $result->getOriginalTransactionKey());
    }
}
