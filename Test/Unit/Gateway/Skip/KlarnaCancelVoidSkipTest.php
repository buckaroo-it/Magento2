<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getBuckarooDatarequestKey')->willReturn(null);
        $orderMock->method('getIncrementId')->willReturn('000000123');

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
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getBuckarooDatarequestKey')->willReturn('datarequest-123');
        $orderMock->method('getIncrementId')->willReturn('000000123');

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
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getBuckarooReservationNumber')->willReturn(null);
        $orderMock->method('getIncrementId')->willReturn('000000123');

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
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getBuckarooReservationNumber')->willReturn('reservation-123');
        $orderMock->method('getIncrementId')->willReturn('000000123');

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
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getBuckarooReservationNumber')->willReturn(null);
        $orderMock->method('getIncrementId')->willReturn('000000123');

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
}
