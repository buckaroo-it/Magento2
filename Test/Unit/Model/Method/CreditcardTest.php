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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Gateway\Http\TransactionBuilder\Order;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Model\Method\Creditcard;

class CreditcardTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Creditcard::class;

    /**
     * Test the assignData method.
     */
    public function testAssignData()
    {
        $data = $this->getObject(DataObject::class);
        $data->setBuckarooSkipValidation(0);
        $data->setAdditionalData([
            'buckaroo_skip_validation' => 1,
            'card_type' => 'maestro'
        ]);

        $infoInstanceMock = $this->getFakeMock(InfoInterface::class)
            ->setMethods(['setAdditionalInformation'])
            ->getMockForAbstractClass();
        $infoInstanceMock->expects($this->exactly(3))->method('setAdditionalInformation')->withConsecutive(
            ['buckaroo_skip_validation', 0],
            ['buckaroo_skip_validation', 1],
            ['card_type', 'maestro']
        );

        $instance = $this->getInstance();
        $instance->setData('info_instance', $infoInstanceMock);

        $result = $instance->assignData($data);
        $this->assertInstanceOf(Creditcard::class, $result);
    }

    /**
     * Test the canCapture method on the happy path.
     */
    public function testCanCapture()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn('noorder');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertTrue($instance->canCapture());
    }

    /**
     * Test the canCapture method on the less happy path.
     */
    public function testCanCaptureDisabled()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())->method('getValue')->willReturn('order');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertFalse($instance->canCapture());
    }

    /**
     * Test the getOrderTransactionBuilder method.
     */
    public function testGetOrderTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation', 'setAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->once())->method('getAdditionalInformation')
            ->with('card_type')
            ->willReturn($fixture['card_type']);
        $paymentMock->expects($this->once())->method('setAdditionalInformation')->with('skip_push', 1);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setServices'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Pay', $services['Action']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterfaceMock = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $instance->setData('info_instance', $infoInterfaceMock);

        $this->assertEquals($orderMock, $instance->getOrderTransactionBuilder($paymentMock));
    }

    /**
     * Test the getCaptureTransactionBuilder method.
     */
    public function testGetCaptureTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
            'transaction_key' => 'key!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->exactly(2))
            ->method('getAdditionalInformation')
            ->withConsecutive(['card_type'], [Creditcard::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY])
            ->willReturnOnConsecutiveCalls($fixture['card_type'], $fixture['transaction_key']);

        $orderMock = $this->getFakeMock(Order::class)
            ->setMethods(['setOrder', 'setMethod', 'setChannel', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setChannel')->with('CallCenter')->willReturnSelf();
        $orderMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with($fixture['transaction_key'])
            ->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Capture', $services['Action']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getCaptureTransactionBuilder($paymentMock));
    }

    /**
     * Test the getAuthorizeTransactionBuild method.
     */
    public function testGetAuthorizeTransactionBuilder()
    {
        $fixture = [
            'card_type' => 'fooname',
            'order' => 'orderrr!',
            'transaction_key' => 'key!',
        ];

        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getOrder', 'getAdditionalInformation'])
            ->getMock();
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($fixture['order']);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('card_type')
            ->willReturn($fixture['card_type']);

        $orderMock = $this->getFakeMock(Order::class)->setMethods(['setOrder', 'setMethod', 'setServices'])->getMock();
        $orderMock->expects($this->once())->method('setOrder')->with($fixture['order'])->willReturnSelf();
        $orderMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $orderMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($fixture, $orderMock) {
                $this->assertEquals($fixture['card_type'], $services['Name']);
                $this->assertEquals('Authorize', $services['Action']);

                return $orderMock;
            }
        );

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)->setMethods(['get'])->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('order')->willReturn($orderMock);

        $infoInterface = $this->getFakeMock(InfoInterface::class)->getMockForAbstractClass();

        $instance = $this->getInstance(['transactionBuilderFactory' => $trxFactoryMock]);
        $instance->setData('info_instance', $infoInterface);

        $this->assertEquals($orderMock, $instance->getAuthorizeTransactionBuilder($paymentMock));
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
        $paymentMock->expects($this->exactly(2))
            ->method('getAdditionalInformation')
            ->withConsecutive(['card_type'], [Creditcard::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY])
            ->willReturnOnConsecutiveCalls($fixture['card_type'], 'getAdditionalInformation');

        $trxFactoryMock = $this->getFakeMock(TransactionBuilderFactory::class)
            ->setMethods(['get', 'setOrder', 'setMethod', 'setChannel', 'setOriginalTransactionKey', 'setServices'])
            ->getMock();
        $trxFactoryMock->expects($this->once())->method('get')->with('refund')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setOrder')->with('orderrr!')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setMethod')->with('TransactionRequest')->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setChannel')->with('CallCenter')->willReturnSelf();
        $trxFactoryMock->expects($this->once())
            ->method('setOriginalTransactionKey')
            ->with('getAdditionalInformation')
            ->willReturnSelf();
        $trxFactoryMock->expects($this->once())->method('setServices')->willReturnCallback(
            function ($services) use ($trxFactoryMock) {
                $services['Name'] = 'creditcard';
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
