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
use Buckaroo\Transaction\Response\TransactionResponse as SDKTransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class TransferDetailsHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var SDKTransactionResponse $transactionResponse */
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        $transferDetails = $this->getTransferDetails($transactionResponse);
        $payment->setAdditionalInformation('transfer_details', $transferDetails);
    }

    /**
     * Extract the transfer service parameters from the transaction response.
     *
     * @param SDKTransactionResponse $transactionResponse
     *
     * @return array
     */
    protected function getTransferDetails($transactionResponse): array
    {
        $serviceParameters = ($i = array_search('transfer', array_column($transactionResponse->data('Services') ?? [], 'Name'))) !== false
            ? array_column($transactionResponse->data('Services')[$i]['Parameters'], 'Value', 'Name')
            : [];

        return [
            'transfer_amount'            => $transactionResponse->getAmount(),
            'transfer_paymentreference'  => $serviceParameters['PaymentReference'] ?? '',
            'transfer_accountholdername' => $serviceParameters['AccountHolderName'] ?? '',
            'transfer_iban'              => $serviceParameters['IBAN'] ?? '',
            'transfer_bic'               => $serviceParameters['BIC'] ?? '',
        ];
    }
}
