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

namespace Buckaroo\Magento2\Test\Unit\Model\Service\Order;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;

/**
 * Whether a reservation was already released is not this class's decision to make: the payment
 * void during order cancellation releases it without ever coming through here, so the answer
 * has to come from ReservationCancellationState, which reads persisted state rather than
 * whichever payment instance a caller happens to hold.
 *
 * @see \Buckaroo\Magento2\Test\Unit\Model\Service\Order\ReservationCancellationStateTest
 */
class CancelRemainingReservationTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Service\Order\CancelRemainingReservation';

    /**
     * The regression: Magento's payment void released the reservation first, so this call is a
     * duplicate the gateway rejects with 491 "reservation has status PartiallyCancelled".
     */
    public function testAReservationReleasedElsewhereIsNotCancelledAgain(): void
    {
        $instance = $this->makeService($this->makeCommandManager($this->never()), true);

        $this->assertFalse($instance->execute($this->makeOrder()));
    }

    public function testAnUntouchedReservationIsCancelled(): void
    {
        $instance = $this->makeService($this->makeCommandManager($this->once()));

        $this->assertTrue($instance->execute($this->makeOrder()));
    }

    /**
     * A gateway rejection is reported as a failure rather than thrown, so the surrounding
     * cancellation still completes.
     */
    public function testAGatewayRejectionIsReportedAsFailure(): void
    {
        $commandManager = $this->makeCommandManager($this->once());
        $commandManager->method('executeByCode')
            ->willThrowException(new \Exception('CancelReservation is not allowed'));

        $instance = $this->makeService($commandManager);

        $this->assertFalse($instance->execute($this->makeOrder()));
    }

    public function testADifferentOrderIsStillCancelled(): void
    {
        $instance = $this->makeService($this->makeCommandManager($this->exactly(2)));

        $this->assertTrue($instance->execute($this->makeOrder(false, '000000020')));
        $this->assertTrue($instance->execute($this->makeOrder(false, '000000021')));
    }

    /**
     * @param mixed $expectation
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeCommandManager($expectation)
    {
        $commandManager = $this->getFakeMock('Magento\Payment\Gateway\Command\CommandManagerInterface')
            ->getMock();
        $commandManager->expects($expectation)->method('executeByCode');

        return $commandManager;
    }

    /**
     * @param object $commandManager
     * @param bool   $alreadyCancelled
     *
     * @return object
     */
    private function makeService($commandManager, bool $alreadyCancelled = false)
    {
        $cancellationState = $this->getFakeMock(
            'Buckaroo\Magento2\Model\Service\Order\ReservationCancellationState'
        )->getMock();
        $cancellationState->method('isCancelled')->willReturn($alreadyCancelled);

        return $this->getInstance([
            'klarnaKpCommandManager' => $commandManager,
            'klarnaCommandManager' => $commandManager,
            'cancellationState' => $cancellationState,
        ]);
    }

    /**
     * @param bool   $voided
     * @param string $incrementId
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeOrder(bool $voided = false, string $incrementId = '000000020')
    {
        $payment = $this->getFakeMock('Magento\Sales\Model\Order\Payment')->getMock();
        $payment->method('getMethod')->willReturn(Klarnakp::CODE);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            function ($key = null) use ($voided) {
                return $key === 'voided_by_buckaroo' ? $voided : 'reservation-number';
            }
        );

        $order = $this->getFakeMock(\Buckaroo\Magento2\Test\Unit\Stubs\OrderStub::class)->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getBuckarooReservationNumber')->willReturn('a-reservation');

        return $order;
    }
}
