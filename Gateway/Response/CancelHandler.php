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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Service\Order\ReservationCancellationState;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CancelHandler implements HandlerInterface
{
    /**
     * @var ReservationCancellationState
     */
    private ReservationCancellationState $cancellationState;

    /**
     * @param ReservationCancellationState $cancellationState
     */
    public function __construct(ReservationCancellationState $cancellationState)
    {
        $this->cancellationState = $cancellationState;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $this->cancellationState->markCancelled($payment);
    }
}
