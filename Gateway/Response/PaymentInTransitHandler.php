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
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentInTransitHandler implements HandlerInterface
{
    public const BUCKAROO_PAYMENT_IN_TRANSIT = 'buckaroo_payment_in_transit';

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        // Skip if refund was already completed via group transactions
        if (isset($response['group_transaction_refund_complete'])
            && $response['group_transaction_refund_complete'] === true
        ) {
            return;
        }

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
     * @param OrderPaymentInterface $payment
     * @param bool                  $inTransit
     */
    public function setPaymentInTransit(OrderPaymentInterface $payment, bool $inTransit = true): void
    {
        $payment->setAdditionalInformation(self::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
    }
}
