<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class ReservationNumberHandler implements HandlerInterface
{
    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
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
                return;
            }

            if (isset($transactionResponse->getServiceParameters()['klarnakp_reservationnumber'])) {
                $order->setBuckarooReservationNumber(
                    $transactionResponse->getServiceParameters()['klarnakp_reservationnumber']
                );
                $order->save();
            }
        }
    }
}
