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
use Buckaroo\Magento2\Model\Service\Order\ReservationNumberStore;
use Magento\Framework\Exception\LocalizedException;

class ReservationNumberDataBuilder extends AbstractDataBuilder
{
    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @var ReservationNumberStore
     */
    private ReservationNumberStore $reservationNumberStore;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param ReservationNumberStore  $reservationNumberStore
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        ReservationNumberStore $reservationNumberStore
    ) {
        $this->logger = $logger;
        $this->reservationNumberStore = $reservationNumberStore;
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
        $reservationNumber = $this->reservationNumberStore->resolve($order);

        if ($reservationNumber === null) {
            $errorMessage = sprintf(
                'Cannot capture Klarna KP payment for order %s: reservation number is missing. ' .
                'This usually happens when the authorization was not completed properly or the push notification failed.',
                $order->getIncrementId()
            );

            $this->logger->addError('[KLARNA_KP] ' . $errorMessage);

            throw new LocalizedException(__($errorMessage));
        }

        return ['reservationNumber' => $reservationNumber];
    }
}
