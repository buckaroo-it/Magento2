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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Skip\KlarnaCancelVoidSkip;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class KlarnaCancelVoidSkipTest extends TestCase
{
    /**
     * @var KlarnaCancelVoidSkip
     */
    private KlarnaCancelVoidSkip $klarnaCancelVoidSkip;

    /**
     * @var BuckarooLoggerInterface|MockObject
     */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(BuckarooLoggerInterface::class);
        $this->klarnaCancelVoidSkip = new KlarnaCancelVoidSkip($this->loggerMock);
    }

    public function testSkipWhenKlarnaMorHasNoDataRequestKey(): void
    {
        $orderMock = $this->createOrderMock();

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $this->loggerMock->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('no reservation reference found'));

        $result = $this->klarnaCancelVoidSkip->isSkip(['payment' => $paymentDOMock]);

        $this->assertTrue($result);
    }

    public function testDoNotSkipWhenKlarnaMorHasDataRequestKey(): void
    {
        $orderMock = $this->createOrderMock('datarequest-123');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $this->loggerMock->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('Proceeding with CancelReservation'));

        $result = $this->klarnaCancelVoidSkip->isSkip(['payment' => $paymentDOMock]);

        $this->assertFalse($result);
    }

    public function testSkipWhenKlarnaKpHasNoReservationNumber(): void
    {
        $orderMock = $this->createOrderMock();

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')->willReturn(null);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $result = $this->klarnaCancelVoidSkip->isSkip(['payment' => $paymentDOMock]);

        $this->assertTrue($result);
    }

    public function testDoNotSkipWhenKlarnaKpHasReservationNumberOnOrder(): void
    {
        $orderMock = $this->createOrderMock(null, 'reservation-123');

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $result = $this->klarnaCancelVoidSkip->isSkip(['payment' => $paymentDOMock]);

        $this->assertFalse($result);
    }

    public function testDoNotSkipWhenKlarnaKpHasReservationNumberInPaymentAdditionalInformation(): void
    {
        $orderMock = $this->createOrderMock();

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarnakp');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(function ($key) {
                if ($key === 'buckaroo_reservation_number') {
                    return 'reservation-456';
                }
                return null;
            });

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        $result = $this->klarnaCancelVoidSkip->isSkip(['payment' => $paymentDOMock]);

        $this->assertFalse($result);
    }

    /**
     * @param string|null $dataRequestKey
     * @param string|null $reservationNumber
     * @return Order|MockObject
     */
    private function createOrderMock(?string $dataRequestKey = null, ?string $reservationNumber = null)
    {
        $orderMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIncrementId', 'getBuckarooDatarequestKey', 'getBuckarooReservationNumber'])->getMock();

        $orderMock->method('getBuckarooDatarequestKey')->willReturn($dataRequestKey);
        $orderMock->method('getBuckarooReservationNumber')->willReturn($reservationNumber);
        $orderMock->method('getIncrementId')->willReturn('000000123');

        return $orderMock;
    }
}
