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

class TransactionIdHandler implements HandlerInterface
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response): void
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
        $transactionKey = $transactionResponse->getTransactionKey();

        if (!empty($transactionKey)) {
            $payment->setTransactionId($transactionKey);
            /**
             * Save the payment's transaction key.
             */
            if ($this->shouldSaveTransactionKey()) {
                $payment->setAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey);
            }

            $payment->setIsTransactionClosed($this->shouldCloseTransaction());
            $payment->setShouldCloseParentTransaction($this->shouldCloseParentTransaction($payment));
        }
    }

    /**
     * Whether a transaction key should be saved on additional information
     *
     * @return bool
     */
    protected function shouldSaveTransactionKey(): bool
    {
        return true;
    }

    /**
     * Whether transaction should be closed
     *
     * @return bool
     */
    protected function shouldCloseTransaction(): bool
    {
        return true;
    }

    /**
     * Whether parent transaction should be closed
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function shouldCloseParentTransaction(OrderPaymentInterface $payment): bool
    {
        return true;
    }
}
