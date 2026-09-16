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
     *
     * @param string     $paymentMethod
     * @param bool       $hasReservationNumber
     * @param array|null $serviceParameters
     *
     * @throws \Exception
     */
    #[DataProvider('reservationNumberDataProvider')]
    public function testHandle(
        string $paymentMethod,
        bool $hasReservationNumber,
        ?array $serviceParameters
    ): void {
        $this->orderPaymentMock
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod == 'buckaroo_magento2_klarnakp') {
            $orderMock = $this->getMockBuilder(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getBuckarooReservationNumber', 'setBuckarooReservationNumber'])->getMock();

            $orderMock
                ->method('getBuckarooReservationNumber')
                ->willReturn($hasReservationNumber ? '123456' : null);

            // The handler no longer writes the order itself: the store owns every copy of the
            // number, so the expectation is on the store rather than on the order repository.
            if (!$hasReservationNumber && $serviceParameters !== null) {
                $this->transactionResponse
                    ->method('getServiceParameters')
                    ->willReturn($serviceParameters);

                $this->reservationNumberStoreMock
                    ->expects($this->once())
                    ->method('save')
                    ->with($orderMock, $serviceParameters['klarnakp_reservationnumber']);
            } else {
                $this->reservationNumberStoreMock->expects($this->never())->method('save');
            }

            $this->orderPaymentMock
                ->method('getOrder')
                ->willReturn($orderMock);
        }

        $this->reservationNumberHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public static function reservationNumberDataProvider(): array
    {
        return [
            [
                'paymentMethod' => 'buckaroo_magento2_klarnakp',
                'hasReservationNumber' => false,
                'serviceParameters' => ['klarnakp_reservationnumber' => '123456']
            ],
            [
                'paymentMethod' => 'buckaroo_magento2_klarnakp',
                'hasReservationNumber' => true,
                'serviceParameters' => null
            ],
            [
                'paymentMethod' => 'buckaroo_magento2_other',
                'hasReservationNumber' => false,
                'serviceParameters' => null
            ]
        ];
    }
}
