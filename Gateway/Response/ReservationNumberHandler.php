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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Transaction\Status\Response;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class ReservationNumberHandler implements HandlerInterface
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
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var TransactionResponse $transaction */
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        if ($payment->getMethod() == 'buckaroo_magento2_klarnakp') {
            $order = $payment->getOrder();

            if ($order->getBuckarooReservationNumber()) {
                $this->logger->addDebug(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Reservation number already set for order %s: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getIncrementId(),
                    $order->getBuckarooReservationNumber()
                ));
                return;
            }

            $serviceParameters = $transactionResponse->getServiceParameters();
            $statusCode = $transactionResponse->getStatusCode();

            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [%s:%s] - Processing authorization response for order %s | '
                . 'statusCode: %s | serviceParameters: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $statusCode,
                json_encode($serviceParameters)
            ));

            if (isset($serviceParameters['klarnakp_reservationnumber'])) {
                $reservationNumber = $serviceParameters['klarnakp_reservationnumber'];
                $order->setBuckarooReservationNumber($reservationNumber);
                $order->save();

                $this->logger->addDebug(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Successfully saved reservation number for order %s: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getIncrementId(),
                    $reservationNumber
                ));
            } elseif ($this->isPendingStatus((int)$statusCode)) {
                 $this->logger->addDebug(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Pending status %s for order %s, '
                    . 'reservation number expected after customer completes redirect flow.',
                    __METHOD__,
                    __LINE__,
                    $statusCode,
                    $order->getIncrementId()
                ));
            } else {
                $this->logger->addError(sprintf(
                    '[KLARNA_KP] | [%s:%s] - WARNING: No reservation number in response for order %s! ' .
                    'Status: %s | Available service parameters: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getIncrementId(),
                    $statusCode,
                    json_encode(array_keys($serviceParameters))
                ));
            }
        }
    }

    /**
     * Check if the status code indicates a pending/redirect state where the
     * reservation number is not yet available.
     *
     * @param int $statusCode
     * @return bool
     */
    private function isPendingStatus(int $statusCode): bool
    {
        return in_array($statusCode, [
            Response::STATUSCODE_WAITING_ON_USER_INPUT,  // 790
            Response::STATUSCODE_PENDING_PROCESSING,     // 791
            Response::STATUSCODE_WAITING_ON_CONSUMER,    // 792
            Response::STATUSCODE_PAYMENT_ON_HOLD,        // 793
            Response::STATUSCODE_PENDING_APPROVAL,       // 794
        ]);
    }
}
