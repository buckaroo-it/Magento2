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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\Service\UninvoicedCaptures;
use PHPUnit\Framework\Attributes\DataProvider;

class UninvoicedCapturesTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = UninvoicedCaptures::class;

    public function testCapturesAddUpAndKeepTheLatestTransactionKey(): void
    {
        [$order, $payment] = $this->makeOrder();
        $captures = new UninvoicedCaptures();

        $captures->record($payment, 'FIRST', 34.00);
        $captures->record($payment, 'SECOND', 32.15);

        $this->assertSame(66.15, $captures->getAmount($order));
        $this->assertSame('SECOND', $captures->getTransactionKey($order));
    }

    public function testNothingIsRecordedForAnEmptyCapture(): void
    {
        [$order, $payment] = $this->makeOrder();
        $captures = new UninvoicedCaptures();

        $captures->record($payment, 'KEY', 0.0);

        $this->assertNull($payment->getAdditionalInformation(UninvoicedCaptures::ADDITIONAL_INFORMATION_KEY));
        $this->assertSame(0.0, $captures->getAmount($order));
    }

    public function testInvoicingUsesUpTheRecordedAmount(): void
    {
        [$order, $payment] = $this->makeOrder();
        $captures = new UninvoicedCaptures();
        $captures->record($payment, 'KEY', 66.15);

        $captures->consume($order, 52.15);
        $this->assertSame(14.0, $captures->getAmount($order));

        $captures->consume($order, 20.00);
        $this->assertSame(0.0, $captures->getAmount($order), 'Never below zero');
        $this->assertSame('KEY', $captures->getTransactionKey($order));
    }

    public function testRestorePutsBackWhatSnapshotTook(): void
    {
        [$order, $payment] = $this->makeOrder();
        $captures = new UninvoicedCaptures();
        $captures->record($payment, 'KEY', 66.15);

        $snapshot = $captures->snapshot($order);
        $captures->consume($order, 66.15);
        $captures->restore($order, $snapshot);

        $this->assertSame(66.15, $captures->getAmount($order));
    }

    public function testRestoringAnEmptySnapshotRemovesTheRecord(): void
    {
        [$order, $payment] = $this->makeOrder();
        $captures = new UninvoicedCaptures();

        $snapshot = $captures->snapshot($order);
        $captures->record($payment, 'KEY', 10.00);
        $captures->restore($order, $snapshot);

        $this->assertNull($payment->getAdditionalInformation(UninvoicedCaptures::ADDITIONAL_INFORMATION_KEY));
    }

    public function testAnOrderWithoutPaymentHasNothingRecorded(): void
    {
        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn(null);
        $captures = new UninvoicedCaptures();

        $this->assertNull($captures->snapshot($order));
        $captures->restore($order, ['amount' => 1.0]);
        $captures->consume($order, 1.0);
        $this->assertSame(0.0, $captures->getAmount($order));
    }

    /**
     * Orders captured in Plaza before this record existed carry the capture only as the payment's
     * paid amount, with nothing paid on the order itself.
     *
     * @param array $additionalInformation
     * @param float $paymentAmountPaid
     * @param float $orderTotalPaid
     * @param float $expected
     */
    #[DataProvider('legacyProvider')]
    public function testACaptureRecordedByAnOlderVersionIsPickedUp(
        array $additionalInformation,
        float $paymentAmountPaid,
        float $orderTotalPaid,
        float $expected
    ): void {
        [$order, $payment] = $this->makeOrder($additionalInformation, $paymentAmountPaid, $orderTotalPaid);
        $captures = new UninvoicedCaptures();

        $this->assertSame($expected, $captures->getAmount($order));

        if ($expected > 0) {
            $this->assertSame('PLAZA', $captures->getTransactionKey($order));
            $this->assertSame(
                ['amount' => $expected, 'transaction_key' => 'PLAZA'],
                $payment->getAdditionalInformation(UninvoicedCaptures::ADDITIONAL_INFORMATION_KEY),
                'Stored, because the fields it came from are rewritten once an invoice is paid'
            );
        }
    }

    public static function legacyProvider(): array
    {
        $captured = [
            'buckaroo_capture_transaction_key' => 'PLAZA',
            'buckaroo_already_captured' => true,
            InvoiceHandlingOptions::INVOICE_HANDLING => InvoiceHandlingOptions::SHIPMENT,
        ];

        return [
            'captured in Plaza, not invoiced' => [$captured, 66.15, 0.0, 66.15],
            'an invoice already paid something' => [$captured, 66.15, 52.15, 0.0],
            'invoice on payment' => [
                [InvoiceHandlingOptions::INVOICE_HANDLING => InvoiceHandlingOptions::PAYMENT] + $captured,
                66.15,
                0.0,
                0.0,
            ],
            'no capture flag' => [['buckaroo_already_captured' => false] + $captured, 66.15, 0.0, 0.0],
            'no capture key' => [['buckaroo_capture_transaction_key' => ''] + $captured, 66.15, 0.0, 0.0],
            'nothing paid on the payment' => [$captured, 0.0, 0.0, 0.0],
        ];
    }

    public function testARecordedValueWinsOverTheOlderFields(): void
    {
        [$order] = $this->makeOrder([
            'buckaroo_capture_transaction_key' => 'PLAZA',
            'buckaroo_already_captured' => true,
            InvoiceHandlingOptions::INVOICE_HANDLING => InvoiceHandlingOptions::SHIPMENT,
            UninvoicedCaptures::ADDITIONAL_INFORMATION_KEY => ['amount' => 0, 'transaction_key' => 'PLAZA'],
        ], 66.15, 0.0);

        $this->assertSame(0.0, (new UninvoicedCaptures())->getAmount($order));
    }

    /**
     * A payment whose additional information behaves like the real one, on an order.
     *
     * @param array $additionalInformation
     * @param float $paymentAmountPaid
     * @param float $orderTotalPaid
     *
     * @return array{0: object, 1: object}
     */
    private function makeOrder(
        array $additionalInformation = [],
        float $paymentAmountPaid = 0.0,
        float $orderTotalPaid = 0.0
    ): array {
        $store = new \Magento\Framework\DataObject($additionalInformation);

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
        $payment->method('unsAdditionalInformation')->willReturnCallback(
            static function ($key) use ($store, $payment) {
                $store->unsetData($key);
                return $payment;
            }
        );
        $payment->method('getAmountPaid')->willReturn($paymentAmountPaid);

        $order = $this->getFakeMock('Magento\Sales\Model\Order')->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getTotalPaid')->willReturn($orderTotalPaid);

        return [$order, $payment];
    }
}
