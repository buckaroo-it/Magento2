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

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentDetailsHandler implements HandlerInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var BuckarooResponseDataInterface
     */
    protected $buckarooResponseData;

    /**
     * Constructor
     *
     * @param Data                          $helper
     * @param BuckarooResponseDataInterface $buckarooResponseData
     */
    public function __construct(
        Data $helper,
        BuckarooResponseDataInterface $buckarooResponseData
    ) {
        $this->helper = $helper;
        $this->buckarooResponseData = $buckarooResponseData;
    }

    /**
     * @inheritdoc
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
        $arrayResponse = $transactionResponse->toArray();

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $rawInfo = $this->getTransactionAdditionalInfo($arrayResponse);

        $payment->setTransactionAdditionalInfo(
            \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
            \json_encode($rawInfo)
        );

        // SET BUCKAROO RESPONSE REDIRECT
        $this->buckarooResponseData->setResponse($transactionResponse);
    }

    /**
     * Get array of transaction Additional Info
     *
     * @param array $array
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array): array
    {
        return $this->helper->getTransactionAdditionalInfo($array);
    }
}
