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

namespace Buckaroo\Magento2\Test\Unit\Model\Service\Order;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;

/**
 * The `voided_by_buckaroo` flag lives on a payment INSTANCE. Order\Item::getOrder()
 * lazily loads a fresh order when the item does not already carry one, so the item-cancel
 * observer can hold a payment object that never saw the flag. That produced a
 * second CancelReservation which the gateway refused:
 * "CancelReservation on reservation ee77b0c6-… is not allowed".
 */
class CancelRemainingReservationTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Service\Order\CancelRemainingReservation';

    public function testTheReservationIsOnlyCancelledOncePerRequest(): void
    {
        $commandManager = $this->makeCommandManager($this->once());
        $instance = $this->makeService($commandManager);

        // A fresh order object each time, as Order\Item::getOrder() would hand back.
        $this->assertTrue($instance->execute($this->makeOrder()));
        $this->assertFalse(
            $instance->execute($this->makeOrder()),
            'A second caller in the same request must not send another CancelReservation'
        );
    }

    /**
     * A failed attempt is not retried either - a rejection would only become a second rejection.
     */
    public function testAFailedAttemptIsNotRetriedInTheSameRequest(): void
    {
        $commandManager = $this->makeCommandManager($this->once());
        $commandManager->method('executeByCode')
            ->willThrowException(new \Exception('CancelReservation is not allowed'));

        $instance = $this->makeService($commandManager);

        $this->assertFalse($instance->execute($this->makeOrder()));
        $this->assertFalse($instance->execute($this->makeOrder()));
    }

    /**
     * The instance-level flag still short-circuits when it IS visible.
     */
    public function testAnAlreadyVoidedPaymentIsSkipped(): void
    {
        $instance = $this->makeService($this->makeCommandManager($this->never()));

        $this->assertFalse($instance->execute($this->makeOrder(true)));
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
     *
     * @return object
     */
    private function makeService($commandManager)
    {
        return $this->getInstance([
            'klarnaKpCommandManager' => $commandManager,
            'klarnaCommandManager' => $commandManager,
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
