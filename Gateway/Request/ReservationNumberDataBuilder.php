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
