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

namespace Buckaroo\Magento2\Model\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;

/**
 * Single source of truth for "this reservation has already been cancelled at Buckaroo".
 *
 * A reservation can be released from two unrelated directions: Magento's own payment void
 * during order cancellation, and CancelRemainingReservation when the last open order line is
 * cancelled. Both end up in the same gateway command, so whichever runs second must be able to
 * see what the first one did.
 *
 * The `voided_by_buckaroo` flag alone cannot carry that: it lives on a payment INSTANCE, and
 * Order\Item::getOrder() hands back a freshly loaded order whose payment never saw the flag.
 * Persisting it here - and reading it back from storage - makes the answer independent of which
 * object graph a caller happens to hold.
 */
class ReservationCancellationState
{
    /**
     * Payment additional_information key marking a released reservation.
     */
    public const VOIDED_FLAG = 'voided_by_buckaroo';

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param BuckarooLoggerInterface         $logger
     */
    public function __construct(
        OrderPaymentRepositoryInterface $paymentRepository,
        BuckarooLoggerInterface $logger
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
    }

    /**
     * Whether the reservation behind this payment was already cancelled at the provider.
     *
     * @param OrderPaymentInterface|null $payment
     *
     * @return bool
     */
    public function isCancelled(?OrderPaymentInterface $payment): bool
    {
        if ($payment === null) {
            return false;
        }

        if ($this->readFlag($payment)) {
            return true;
        }

        $persisted = $this->loadPersistedPayment($payment);

        return $persisted !== null && $this->readFlag($persisted);
    }

    /**
     * Record that the reservation was cancelled, durably enough for a later caller to see it.
     *
     * @param OrderPaymentInterface|null $payment
     *
     * @return void
     */
    public function markCancelled(?OrderPaymentInterface $payment): void
    {
        if ($payment === null) {
            return;
        }

        $payment->setAdditionalInformation(self::VOIDED_FLAG, true);

        try {
            $this->paymentRepository->save($payment);
        } catch (\Exception $e) {
            // A cancel that already reached the provider must not be undone by a storage
            // problem; the in-instance flag still guards the rest of this request.
            $this->logger->addError(sprintf(
                '[KLARNA] Could not persist %s for payment %s: %s',
                self::VOIDED_FLAG,
                (string)$payment->getEntityId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Read the flag off a payment instance.
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     */
    private function readFlag(OrderPaymentInterface $payment): bool
    {
        return (bool)$payment->getAdditionalInformation(self::VOIDED_FLAG);
    }

    /**
     * Load the stored counterpart of the given payment, if it has been persisted at all.
     *
     * @param OrderPaymentInterface $payment
     *
     * @return OrderPaymentInterface|null
     */
    private function loadPersistedPayment(OrderPaymentInterface $payment): ?OrderPaymentInterface
    {
        $paymentId = (int)$payment->getEntityId();
        if ($paymentId === 0) {
            return null;
        }

        try {
            return $this->paymentRepository->get($paymentId);
        } catch (\Exception $e) {
            $this->logger->addDebug(sprintf(
                '[KLARNA] Could not reload payment %s to check %s: %s',
                $paymentId,
                self::VOIDED_FLAG,
                $e->getMessage()
            ));

            return null;
        }
    }
}
