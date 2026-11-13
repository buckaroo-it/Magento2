<?php

/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Observer\VoidCm3Payment;
use Buckaroo\Magento2\Test\BaseTest;

class VoidCm3PaymentTest extends BaseTest
{
    protected $instanceClass = VoidCm3Payment::class;

    public function testExecuteNotBuckaroo()
    {
        $paymentMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PaymentStub::class)
            ->onlyMethods(['getMethod', 'getAuthorizationTransaction', 'getAdditionalInformation', 'getMethodInstance', 'createCreditNoteRequest'])->getMock();
        $paymentMock->method('getMethod')->willReturn('fake_method');
        $paymentMock->expects($this->never())->method('getAuthorizationTransaction');
        $paymentMock->expects($this->never())->method('getAdditionalInformation');
        $paymentMock->expects($this->never())->method('getMethodInstance');
        $paymentMock->expects($this->never())->method('createCreditNoteRequest');

        $observerMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)->onlyMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteNoInvoiceKey()
    {
        $paymentMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\PaymentStub::class)
            ->onlyMethods(['getMethod', 'getAuthorizationTransaction', 'getAdditionalInformation', 'getMethodInstance', 'createCreditNoteRequest'])->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_method');
        $paymentMock->method('getAuthorizationTransaction')->willReturn(false);
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn(null);
        $paymentMock->expects($this->never())->method('getMethodInstance');
        $paymentMock->expects($this->never())->method('createCreditNoteRequest');

        $observerMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)->onlyMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteCreditNoteMethodCalled()
    {
        // Create a method instance mock that has the createCreditNoteRequest method
        $methodInstanceMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\StdObjectStub::class)
            ->getMock();
        $methodInstanceMock->expects($this->never())->method('createCreditNoteRequest');

        $voidCommandMock = $this->getFakeMock(\Magento\Payment\Gateway\CommandInterface::class)->getMock();
        $voidCommandMock->expects($this->once())->method('execute')->with($this->arrayHasKey('payment'));

        $paymentMock = $this->getFakeMock(Payment::class)
            ->onlyMethods([
                'getMethod',
                'getAuthorizationTransaction',
                'getAdditionalInformation',
                'getMethodInstance'
            ])
            ->getMock();
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_method');
        $paymentMock->method('getAuthorizationTransaction')->willReturn(false);
        $paymentMock->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('key');
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $observerMock = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub::class)->onlyMethods(['getPayment'])->getMock();
        $observerMock->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance(['voidCommand' => $voidCommandMock]);
        $instance->execute($observerMock);
    }
}
