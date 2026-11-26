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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Push;

use Buckaroo\Magento2\Model\Service\UninvoicedCaptures;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;

/**
 * A capture that arrives before the order ships (a capture made in Buckaroo Plaza) creates no
 * invoice. It has to be recorded so the invoice created on shipment does not capture it again.
 */
class DeferredCaptureRecordTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Push\DefaultProcessor';

    public function testACaptureWithoutInvoiceIsRecordedForTheInvoicesThatFollow(): void
    {
        [$instance, $order, $payment] = $this->makeProcessor('PLAZA_CAPTURE_KEY');

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 66.15], $instance);

        $captures = new UninvoicedCaptures();
        $this->assertSame(66.15, $captures->getAmount($order));
        $this->assertSame('PLAZA_CAPTURE_KEY', $captures->getTransactionKey($order));
    }

    public function testTwoPartialCapturesAddUp(): void
    {
        [$instance, $order, $payment] = $this->makeProcessor('SECOND_KEY');

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 34.00], $instance);
        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 32.15], $instance);

        $this->assertSame(66.15, (new UninvoicedCaptures())->getAmount($order));
        $this->assertSame(66.15, $payment->getBaseAmountPaidOnline(), 'Added up, not overwritten');
    }

    public function testTheCaptureIsAddedToThePaidAmountsInBothCurrencies(): void
    {
        $paymentRepository = $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class);
        [$instance, , $payment] = $this->makeProcessor('CAPTURE_KEY', 1.1, $paymentRepository);
        $payment->setBaseAmountPaid(10.0);
        $payment->setAmountPaid(11.0);
        $payment->setBaseAmountPaidOnline(10.0);

        $payment->expects($this->once())->method('setTransactionId')->with('CAPTURE_KEY');
        $payment->expects($this->once())->method('addTransaction')->with('capture', null, true);
        $payment->expects($this->once())->method('setIsTransactionClosed')->with(0);
        $payment->expects($this->once())->method('setShouldCloseParentTransaction')->with(false);
        $paymentRepository->expects($this->once())->method('save')->with($payment);

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 20.0], $instance);

        $this->assertSame(30.0, $payment->getBaseAmountPaid());
        $this->assertEqualsWithDelta(33.0, $payment->getAmountPaid(), 0.0000001);
        $this->assertSame(30.0, $payment->getBaseAmountPaidOnline());
        $this->assertSame('CAPTURE_KEY', $payment->getAdditionalInformation('buckaroo_capture_transaction_key'));
        $this->assertTrue($payment->getAdditionalInformation('buckaroo_already_captured'));
        $this->assertSame(2, $payment->getAdditionalInformation('invoice_handling'));
        $this->assertSame(
            ['amount' => 20.0, 'transaction_key' => 'CAPTURE_KEY'],
            $payment->getAdditionalInformation(UninvoicedCaptures::ADDITIONAL_INFORMATION_KEY)
        );
    }

    public function testAFirstCaptureWithoutRateCountsTheCapturedAmountOnly(): void
    {
        [$instance, , $payment] = $this->makeProcessor('', null);

        $payment->expects($this->never())->method('setTransactionId');

        $this->invokeArgs('recordCaptureWithoutInvoice', [$payment, 20.0], $instance);

        $this->assertSame(20.0, $payment->getBaseAmountPaid());
        $this->assertSame(20.0, $payment->getAmountPaid());
        $this->assertNull($payment->getAdditionalInformation('buckaroo_capture_transaction_key'));
    }

    /**
     * @param string     $transactionKey
     * @param float|null $rate
     * @param mixed      $paymentRepository
     *
     * @return array{0: object, 1: object, 2: object}
     */
    private function makeProcessor(string $transactionKey, ?float $rate = 1.0, $paymentRepository = null): array
    {
        $store = new \Magento\Framework\DataObject();
        $amounts = new \Magento\Framework\DataObject();

        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn($key = null) => $key === null ? $store->getData() : $store->getData($key)
        );
        $payment->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use ($store, $payment) {
                $store->setData($key, $value);
                return $payment;
            }
        );
        foreach (['BaseAmountPaidOnline', 'BaseAmountPaid', 'AmountPaid'] as $field) {
            $payment->method('get' . $field)->willReturnCallback(static fn() => $amounts->getData($field));
            $payment->method('set' . $field)->willReturnCallback(
                static function ($value) use ($amounts, $field, $payment) {
                    $amounts->setData($field, $value);
                    return $payment;
                }
            );
        }

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseToOrderRate')->willReturn($rate);

        $pushRequest = $this->getFakeMock(PushRequestInterfaceStub::class)->getMock();
        $pushRequest->method('getTransactions')->willReturn($transactionKey);

        $instance = $this->getInstance(array_filter([
            'uninvoicedCaptures' => new UninvoicedCaptures(),
            'paymentRepository' => $paymentRepository,
        ]));
        $this->setProperty('order', $order, $instance);
        $this->setProperty('pushRequest', $pushRequest, $instance);

        return [$instance, $order, $payment];
    }
}
