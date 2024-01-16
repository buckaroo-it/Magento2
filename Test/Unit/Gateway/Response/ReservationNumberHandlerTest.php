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

use Buckaroo\Magento2\Gateway\Response\ReservationNumberHandler;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class ReservationNumberHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var ReservationNumberHandler
     */
    protected ReservationNumberHandler $reservationNumberHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationNumberHandler = new ReservationNumberHandler();
    }

    /**
     * @dataProvider reservationNumberDataProvider
     *
     * @param string $paymentMethod
     * @param bool $hasReservationNumber
     * @param array|null $serviceParameters
     * @throws \Exception
     */
    public function testHandle(
        string $paymentMethod,
        bool $hasReservationNumber,
        ?array $serviceParameters
    ): void {
        $this->orderPaymentMock
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod == 'buckaroo_magento2_klarnakp') {
            $orderMock = $this->getMockBuilder(Order::class)
                ->disableOriginalConstructor()
                ->addMethods(['getBuckarooReservationNumber', 'setBuckarooReservationNumber'])
                ->onlyMethods(['save'])
                ->getMock();

            $orderMock
                ->expects($this->once())
                ->method('getBuckarooReservationNumber')
                ->willReturn($hasReservationNumber ? '123456' : null);

            if (!$hasReservationNumber && $serviceParameters !== null) {
                $this->transactionResponse
                    ->expects($this->once())
                    ->method('getServiceParameters')
                    ->willReturn($serviceParameters);
                $orderMock
                    ->expects($this->once())
                    ->method('setBuckarooReservationNumber')
                    ->with($serviceParameters['klarnakp_reservationnumber']);

                $orderMock->expects($this->once())->method('save');
            } else {
                $orderMock->expects($this->never())->method('setBuckarooReservationNumber');
                $orderMock->expects($this->never())->method('save');
            }

            $this->orderPaymentMock
                ->expects($this->once())
                ->method('getOrder')
                ->willReturn($orderMock);
        }

        $this->reservationNumberHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function reservationNumberDataProvider(): array
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
