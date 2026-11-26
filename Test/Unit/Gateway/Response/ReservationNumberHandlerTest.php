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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Response\ReservationNumberHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ReservationNumberStore;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class ReservationNumberHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var ReservationNumberHandler
     */
    protected $reservationNumberHandler;

    /**
     * @var ReservationNumberStore|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $reservationNumberStoreMock;

    protected function setUp(): void
    {
        parent::setUp();

        $loggerMock = $this->createMock(BuckarooLoggerInterface::class);
        $this->reservationNumberStoreMock = $this->createMock(ReservationNumberStore::class);
        $this->reservationNumberHandler = new ReservationNumberHandler(
            $loggerMock,
            $this->reservationNumberStoreMock
        );
    }

    /**
     * @param string      $paymentMethod
     * @param bool        $hasReservationNumber
     * @param array       $serviceParameters
     * @param string|null $expectedSavedNumber null when nothing may be saved
     *
     * @throws \Exception
     */
    #[DataProvider('reservationNumberDataProvider')]
    public function testHandle(
        string $paymentMethod,
        bool $hasReservationNumber,
        array $serviceParameters,
        ?string $expectedSavedNumber
    ): void {
        $this->orderPaymentMock
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $orderMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBuckarooReservationNumber', 'setBuckarooReservationNumber'])->getMock();
        $orderMock
            ->method('getBuckarooReservationNumber')
            ->willReturn($hasReservationNumber ? '123456' : null);

        $this->orderPaymentMock
            ->method('getOrder')
            ->willReturn($orderMock);

        $this->transactionResponse
            ->method('getServiceParameters')
            ->willReturn($serviceParameters);

        // The store owns every copy of the number, so the expectation is on the store.
        if ($expectedSavedNumber !== null) {
            $this->reservationNumberStoreMock
                ->expects($this->once())
                ->method('save')
                ->with($this->identicalTo($orderMock), $expectedSavedNumber);
        } else {
            $this->reservationNumberStoreMock->expects($this->never())->method('save');
        }

        $this->reservationNumberHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public static function reservationNumberDataProvider(): array
    {
        return [
            'klarna kp without stored number saves the response number' => [
                'paymentMethod' => 'buckaroo_magento2_klarnakp',
                'hasReservationNumber' => false,
                'serviceParameters' => ['klarnakp_reservationnumber' => 987654],
                'expectedSavedNumber' => '987654',
            ],
            'klarna kp with stored number saves nothing' => [
                'paymentMethod' => 'buckaroo_magento2_klarnakp',
                'hasReservationNumber' => true,
                'serviceParameters' => ['klarnakp_reservationnumber' => '555555'],
                'expectedSavedNumber' => null,
            ],
            'other method saves nothing even with a reservation number in the response' => [
                'paymentMethod' => 'buckaroo_magento2_other',
                'hasReservationNumber' => false,
                'serviceParameters' => ['klarnakp_reservationnumber' => '123456'],
                'expectedSavedNumber' => null,
            ],
        ];
    }
}
