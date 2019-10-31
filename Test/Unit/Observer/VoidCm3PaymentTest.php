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
namespace TIG\Buckaroo\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Observer\VoidCm3Payment;
use TIG\Buckaroo\Test\BaseTest;

class VoidCm3PaymentTest extends BaseTest
{
    protected $instanceClass = VoidCm3Payment::class;

    public function testExecuteNotBuckaroo()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods([
                'getMethod',
                'getAuthorizationTransaction',
                'getAdditionalInformation',
                'getMethodInstance',
                'createCreditNoteRequest'
            ])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('fake_method');
        $paymentMock->expects($this->never())->method('getAuthorizationTransaction');
        $paymentMock->expects($this->never())->method('getAdditionalInformation');
        $paymentMock->expects($this->never())->method('getMethodInstance');
        $paymentMock->expects($this->never())->method('createCreditNoteRequest');

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteNoInvoiceKey()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods([
                'getMethod',
                'getAuthorizationTransaction',
                'getAdditionalInformation',
                'getMethodInstance',
                'createCreditNoteRequest'
            ])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('tig_buckaroo_method');
        $paymentMock->expects($this->once())->method('getAuthorizationTransaction')->willReturn(false);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn(null);
        $paymentMock->expects($this->never())->method('getMethodInstance');
        $paymentMock->expects($this->never())->method('createCreditNoteRequest');

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }

    public function testExecuteCreditNoteMethodCalled()
    {
        $paymentMock = $this->getFakeMock(Payment::class)
            ->setMethods([
                'getMethod',
                'getAuthorizationTransaction',
                'getAdditionalInformation',
                'getMethodInstance',
                'createCreditNoteRequest'
            ])
            ->getMock();
        $paymentMock->expects($this->once())->method('getMethod')->willReturn('tig_buckaroo_method');
        $paymentMock->expects($this->once())->method('getAuthorizationTransaction')->willReturn(false);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('invoiceKey');
        $paymentMock->expects($this->once())->method('getMethodInstance')->willReturnSelf();
        $paymentMock->expects($this->once())->method('createCreditNoteRequest')->with($paymentMock);

        $observerMock = $this->getFakeMock(Observer::class)->setMethods(['getPayment'])->getMock();
        $observerMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $instance = $this->getInstance();
        $instance->execute($observerMock);
    }
}