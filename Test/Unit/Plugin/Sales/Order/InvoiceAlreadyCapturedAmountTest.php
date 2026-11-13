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

namespace Buckaroo\Magento2\Test\Unit\Plugin\Sales\Order;

use Buckaroo\Magento2\Model\Service\UninvoicedCaptures;
use Buckaroo\Magento2\Plugin\Sales\Order\InvoiceAlreadyCapturedAmount;
use Buckaroo\Magento2\Service\CheckPaymentType;

/**
 * An order captured in Buckaroo Plaza before it shipped was invoiced on shipment by capturing the
 * reservation again, which Buckaroo refuses ("reservation has status FullyCaptured"). No invoice
 * was created and the order stayed unpaid.
 */
class InvoiceAlreadyCapturedAmountTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = InvoiceAlreadyCapturedAmount::class;

    public function testAnInvoiceCoveredByAnEarlierCaptureIsPaidWithoutAGatewayCall(): void
    {
        $captures = $this->makeCaptures(66.15, 'PLAZA_KEY');
        $captures->expects($this->once())->method('consume')->with($this->anything(), 66.15);

        $order = $this->makeOrder('buckaroo_magento2_klarnakp', false);
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $invoice = $this->makeInvoice($order, 66.15);
        $invoice->expects($this->once())->method('setTransactionId')->with('PLAZA_KEY');
        $invoice->expects($this->once())->method('pay');

        $result = $this->plugin($captures)->aroundCapture($invoice, $this->failingProceed());

        $this->assertSame($invoice, $result);
    }

    public function testALaterShipmentStillCoveredIsAlsoPaidFromTheCapture(): void
    {
        $captures = $this->makeCaptures(14.00, 'PLAZA_KEY');
        $captures->expects($this->once())->method('consume')->with($this->anything(), 14.00);

        $invoice = $this->makeInvoice($this->makeOrder('buckaroo_magento2_klarnakp', false), 14.00);
        $invoice->expects($this->once())->method('pay');

        $this->plugin($captures)->aroundCapture($invoice, $this->failingProceed());
    }

    /**
     * Klarna accepts a capture without lines only for exactly what is left on the reservation.
     */
    public function testTheLastKlarnaKpInvoiceCapturesOnlyWhatTheEarlierCaptureLeftOver(): void
    {
        $captures = $this->makeCaptures(34.00, 'PLAZA_KEY');
        $captures->expects($this->once())->method('consume')->with($this->anything(), 34.00);

        $order = $this->makeOrder('buckaroo_magento2_klarnakp', false);
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $invoice = new \Magento\Framework\DataObject(['grand_total' => 66.15]);
        $invoiceMock = $this->makeInvoice($order, 66.15, $invoice);
        $invoiceMock->expects($this->never())->method('pay');

        $remainderDuringCapture = null;
        $proceed = function () use ($invoice, $invoiceMock, &$remainderDuringCapture) {
            $remainderDuringCapture = $invoice->getData(UninvoicedCaptures::INVOICE_CAPTURE_REMAINDER);
            return $invoiceMock;
        };

        $this->plugin($captures)->aroundCapture($invoiceMock, $proceed);

        $this->assertSame(32.15, $remainderDuringCapture);
        $this->assertNull(
            $invoice->getData(UninvoicedCaptures::INVOICE_CAPTURE_REMAINDER),
            'The remainder must not stay on the invoice'
        );
    }

    public function testAFailedRemainderCaptureKeepsTheRecordedAmount(): void
    {
        $captures = $this->makeCaptures(34.00, 'PLAZA_KEY');
        $captures->expects($this->never())->method('consume');

        $invoice = new \Magento\Framework\DataObject();
        $invoiceMock = $this->makeInvoice($this->makeOrder('buckaroo_magento2_klarnakp', false), 66.15, $invoice);

        $this->expectException(\RuntimeException::class);
        try {
            $this->plugin($captures)->aroundCapture($invoiceMock, function () {
                throw new \RuntimeException('Validation failure');
            });
        } finally {
            $this->assertNull($invoice->getData(UninvoicedCaptures::INVOICE_CAPTURE_REMAINDER));
        }
    }

    /**
     * Without order lines on the capture push it is unknown which lines were captured, so a
     * shipment that leaves more to invoice is captured by its own lines as before.
     */
    public function testAPartlyCoveredInvoiceThatIsNotTheLastIsCapturedAsUsual(): void
    {
        $captures = $this->makeCaptures(34.00, 'PLAZA_KEY');
        $captures->expects($this->never())->method('consume');

        $invoice = $this->makeInvoice($this->makeOrder('buckaroo_magento2_klarnakp', true), 52.15);

        $this->assertProceeds($captures, $invoice);
    }

    public function testAPartlyCoveredInvoiceOfAnotherMethodIsCapturedAsUsual(): void
    {
        $captures = $this->makeCaptures(34.00, 'PLAZA_KEY');
        $captures->expects($this->never())->method('consume');

        $invoice = $this->makeInvoice($this->makeOrder('buckaroo_magento2_afterpay20', false), 66.15);

        $this->assertProceeds($captures, $invoice);
    }

    public function testNothingChangesWithoutAnEarlierCapture(): void
    {
        $captures = $this->makeCaptures(0.0, '');
        $captures->expects($this->never())->method('consume');

        $invoice = $this->makeInvoice($this->makeOrder('buckaroo_magento2_klarnakp', false), 66.15);

        $this->assertProceeds($captures, $invoice);
    }

    public function testAnInvoiceThatChargesNothingIsLeftAlone(): void
    {
        $captures = $this->makeCaptures(66.15, 'PLAZA_KEY');
        $captures->expects($this->never())->method('consume');

        $invoice = $this->makeInvoice($this->makeOrder('buckaroo_magento2_klarnakp', false), 0.0);

        $this->assertProceeds($captures, $invoice);
    }

    public function testOtherPaymentProvidersAreLeftAlone(): void
    {
        $captures = $this->makeCaptures(66.15, 'PLAZA_KEY');
        $captures->expects($this->never())->method('getAmount');

        $invoice = $this->makeInvoice($this->makeOrder('checkmo', false), 66.15);

        $this->assertProceeds($captures, $invoice);
    }

    /**
     * @param object $captures
     * @param object $invoice
     *
     * @return void
     */
    private function assertProceeds($captures, $invoice): void
    {
        $called = false;
        $invoice->expects($this->never())->method('pay');

        $result = $this->plugin($captures)->aroundCapture($invoice, function () use (&$called, $invoice) {
            $called = true;
            return $invoice;
        });

        $this->assertTrue($called, 'The invoice must be captured at the gateway');
        $this->assertSame($invoice, $result);
    }

    /**
     * @return \Closure
     */
    private function failingProceed(): \Closure
    {
        return function () {
            $this->fail('The gateway must not be asked to capture again');
        };
    }

    /**
     * @param object $captures
     *
     * @return InvoiceAlreadyCapturedAmount
     */
    private function plugin($captures): InvoiceAlreadyCapturedAmount
    {
        return $this->getInstance([
            'uninvoicedCaptures' => $captures,
            'checkPaymentType' => new CheckPaymentType(),
        ]);
    }

    /**
     * @param float  $amount
     * @param string $transactionKey
     *
     * @return object
     */
    private function makeCaptures(float $amount, string $transactionKey)
    {
        $captures = $this->getFakeMock(UninvoicedCaptures::class)->getMock();
        $captures->method('getAmount')->willReturn($amount);
        $captures->method('getTransactionKey')->willReturn($transactionKey);

        return $captures;
    }

    /**
     * @param string $method
     * @param bool   $canInvoiceAfterThisInvoice
     *
     * @return object
     */
    private function makeOrder(string $method, bool $canInvoiceAfterThisInvoice)
    {
        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethod')->willReturn($method);

        $currency = $this->getFakeMock('Magento\Directory\Model\Currency')->getMock();
        $currency->method('formatTxt')->willReturnCallback(static fn($amount) => 'EUR ' . $amount);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('canInvoice')->willReturn($canInvoiceAfterThisInvoice);
        $order->method('getOrderCurrency')->willReturn($currency);
        $order->method('getIncrementId')->willReturn('300000350');

        return $order;
    }

    /**
     * An invoice mock; with $data, data calls go to that real object so they can be inspected.
     *
     * @param object                              $order
     * @param float                               $grandTotal
     * @param \Magento\Framework\DataObject|null  $data
     *
     * @return object
     */
    private function makeInvoice($order, float $grandTotal, ?\Magento\Framework\DataObject $data = null)
    {
        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getGrandTotal')->willReturn($grandTotal);

        if ($data !== null) {
            $invoice->method('setData')->willReturnCallback(
                static function ($key, $value = null) use ($data, $invoice) {
                    $data->setData($key, $value);
                    return $invoice;
                }
            );
            $invoice->method('unsetData')->willReturnCallback(
                static function ($key = null) use ($data, $invoice) {
                    $data->unsetData($key);
                    return $invoice;
                }
            );
        }

        return $invoice;
    }
}
