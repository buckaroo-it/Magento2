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
            
            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [%s:%s] - Processing authorization response for order %s | serviceParameters: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
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
            } else {
                $this->logger->addError(sprintf(
                    '[KLARNA_KP] | [%s:%s] - WARNING: No reservation number in response for order %s! ' .
                    'Available service parameters: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getIncrementId(),
                    json_encode(array_keys($serviceParameters))
                ));
            }
        }
    }
}
