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

use Buckaroo\Magento2\Observer\VoidCm3Payment;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\ObserverStub;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;

class VoidCm3PaymentTest extends BaseTest
{
    protected $instanceClass = VoidCm3Payment::class;

    public function testExecuteNotBuckaroo()
    {
        $payment = $this->makePayment('fake_method', false, 'key');

        $this->runObserver($payment, $this->expectNoVoid());
    }

    public function testExecuteNoInvoiceKey()
    {
        $payment = $this->makePayment('buckaroo_magento2_method', false, null);

        $this->runObserver($payment, $this->expectNoVoid());
    }

    public function testExecuteWithAuthorizationTransactionDoesNotVoid()
    {
        $payment = $this->makePayment('buckaroo_magento2_method', true, 'key');

        $this->runObserver($payment, $this->expectNoVoid());
    }

    public function testExecuteVoidsThroughTheVoidCommand()
    {
        $payment = $this->makePayment('buckaroo_magento2_method', false, 'key');
        $paymentDataObject = $this->getMockBuilder(PaymentDataObjectInterface::class)->getMock();

        $factory = $this->getFakeMock(PaymentDataObjectFactory::class)->onlyMethods(['create'])->getMock();
        $factory->expects($this->once())->method('create')->with($payment)->willReturn($paymentDataObject);

        $voidCommand = $this->getMockBuilder(CommandInterface::class)->getMock();
        $voidCommand->expects($this->once())->method('execute')->with(['payment' => $paymentDataObject]);

        $this->runObserver($payment, $voidCommand, $factory);
    }

    /**
     * @return object
     */
    private function expectNoVoid()
    {
        $voidCommand = $this->getMockBuilder(CommandInterface::class)->getMock();
        $voidCommand->expects($this->never())->method('execute');

        return $voidCommand;
    }

    /**
     * @param string      $method
     * @param bool        $hasAuthorization
     * @param string|null $invoiceKey
     *
     * @return object
     */
    private function makePayment(string $method, bool $hasAuthorization, ?string $invoiceKey)
    {
        $payment = $this->getFakeMock(Payment::class)
            ->onlyMethods(['getMethod', 'getAuthorizationTransaction', 'getAdditionalInformation'])
            ->getMock();
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getAuthorizationTransaction')->willReturn($hasAuthorization);
        $payment->method('getAdditionalInformation')
            ->willReturnMap([['buckaroo_cm3_invoice_key', $invoiceKey]]);

        return $payment;
    }

    /**
     * @param object      $payment
     * @param object      $voidCommand
     * @param object|null $factory
     */
    private function runObserver($payment, $voidCommand, $factory = null): void
    {
        $observer = $this->getFakeMock(ObserverStub::class)->onlyMethods(['getPayment'])->getMock();
        $observer->method('getPayment')->willReturn($payment);

        $args = ['voidCommand' => $voidCommand];
        if ($factory !== null) {
            $args['paymentDataObjectFactory'] = $factory;
        }

        $this->getInstance($args)->execute($observer);
    }
}
