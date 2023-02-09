<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentInTransitHandler implements HandlerInterface
{
    public const BUCKAROO_PAYMENT_IN_TRANSIT = 'buckaroo_payment_in_transit';

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

        $this->setPaymentInTransit($payment);

        if (!$transactionResponse->hasRedirect()) {
            $this->setPaymentInTransit($payment, false);
        }
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param InfoInterface $payment
     * @param bool $inTransit
     * @return void
     */
    public function setPaymentInTransit(InfoInterface $payment, bool $inTransit = true)
    {
        $payment->setAdditionalInformation(self::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
    }
}
