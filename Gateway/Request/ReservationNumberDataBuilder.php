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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Exception\LocalizedException;

class ReservationNumberDataBuilder extends AbstractDataBuilder
{
    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooLoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $order = $this->getOrder();
        $reservationNumber = $order->getBuckarooReservationNumber();

        if ($reservationNumber === null) {
            $payment = $order->getPayment();
            $reservationNumber = $payment->getAdditionalInformation('buckaroo_reservation_number');

            if ($reservationNumber === null) {
                $errorMessage = sprintf(
                    'Cannot capture Klarna KP payment for order %s: reservation number is missing. ' .
                    'This usually happens when the authorization was not completed properly or the push notification failed.',
                    $order->getIncrementId()
                );

                $this->logger->addError('[KLARNA_KP] ' . $errorMessage);

                throw new LocalizedException(__($errorMessage));
            }

            $this->logger->addWarning(sprintf(
                '[KLARNA_KP] Reservation number for order %s was found in payment additional information but not in order. ' .
                'This indicates a data sync issue. Using value: %s',
                $order->getIncrementId(),
                $reservationNumber
            ));
        }

        return ['reservationNumber' => $reservationNumber];
    }
}
