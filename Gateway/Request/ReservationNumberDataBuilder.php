<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class ReservationNumberDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['reservationNumber' => $this->getOrder()->getBuckarooReservationNumber()];
    }
}
