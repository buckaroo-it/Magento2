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

use Buckaroo\Magento2\Model\Service\Order\ReservationCancellationState;

/**
 * Order 300000019 sent two CancelReservation calls six seconds apart: the payment void
 * released the reservation, then the item-cancel observer released it again and the gateway
 * answered 491 "reservation has status PartiallyCancelled".
 *
 * The reason both ran is that `voided_by_buckaroo` was only ever set on the payment INSTANCE
 * the void happened to hold, while the observer reached the order through
 * Order\Item::getOrder() and got a freshly loaded payment without it.
 */
class ReservationCancellationStateTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = 'Buckaroo\Magento2\Model\Service\Order\ReservationCancellationState';

    /**
     * The regression, expressed directly: marking one instance must be visible to a second,
     * independently loaded instance of the same payment.
     */
    public function testAMarkedReservationIsVisibleToADifferentPaymentInstance(): void
    {
        $marked = $this->makePayment();
        $reloaded = $this->makePayment();

        $repository = $this->makeRepository();
        // The repository is the shared ground truth both callers consult.
        $repository->method('save')->willReturnCallback(
            function ($payment) use ($reloaded) {
                $reloaded->setAdditionalInformation(
                    ReservationCancellationState::VOIDED_FLAG,
                    $payment->getAdditionalInformation(ReservationCancellationState::VOIDED_FLAG)
                );

                return $payment;
            }
        );
        $repository->method('get')->willReturn($reloaded);

        $instance = $this->getInstance(['paymentRepository' => $repository]);

        $this->assertFalse(
            $instance->isCancelled($this->makePayment()),
            'Nothing has been cancelled yet'
        );

        $instance->markCancelled($marked);

        $this->assertTrue(
            $instance->isCancelled($this->makePayment()),
            'A caller holding a different payment object must still see the release'
        );
    }

    public function testMarkingPersistsTheFlag(): void
    {
        $payment = $this->makePayment();

        $repository = $this->makeRepository();
        $repository->expects($this->once())->method('save')->with($payment);

        $this->getInstance(['paymentRepository' => $repository])->markCancelled($payment);

        $this->assertTrue(
            (bool)$payment->getAdditionalInformation(ReservationCancellationState::VOIDED_FLAG)
        );
    }

    /**
     * A cancel that already reached the provider must not be undone by a storage problem.
     */
    public function testAFailedSaveStillLeavesTheInstanceMarked(): void
    {
        $payment = $this->makePayment();

        $repository = $this->makeRepository();
        $repository->method('save')->willThrowException(new \Exception('deadlock'));

        $instance = $this->getInstance(['paymentRepository' => $repository]);
        $instance->markCancelled($payment);

        $this->assertTrue($instance->isCancelled($payment));
    }

    public function testAnUnsavedPaymentIsNotLookedUp(): void
    {
        $payment = $this->makePayment(null);

        $repository = $this->makeRepository();
        $repository->expects($this->never())->method('get');

        $this->assertFalse($this->getInstance(['paymentRepository' => $repository])
            ->isCancelled($payment));
    }

    public function testANullPaymentIsHandled(): void
    {
        $instance = $this->getInstance(['paymentRepository' => $this->makeRepository()]);

        $this->assertFalse($instance->isCancelled(null));
        $instance->markCancelled(null);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function makeRepository()
    {
        return $this->getFakeMock('Magento\Sales\Api\OrderPaymentRepositoryInterface')->getMock();
    }

    /**
     * A real payment object, so additional_information behaves as it does in production.
     *
     * @param int|null $entityId
     *
     * @return \Magento\Sales\Model\Order\Payment
     */
    private function makePayment(?int $entityId = 42)
    {
        $payment = $this->objectManagerHelper->getObject('Magento\Sales\Model\Order\Payment');
        $payment->setEntityId($entityId);

        return $payment;
    }
}
