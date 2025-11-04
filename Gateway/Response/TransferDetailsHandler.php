<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Transaction\Response\TransactionResponse as SDKTransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class TransferDetailsHandler implements HandlerInterface
{
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

    protected function getTransferDetails($transactionResponse): array
    {
        $params = ($i = array_search('transfer', array_column($transactionResponse->data('Services') ?? [], 'Name'))) !== false
            ? array_column($transactionResponse->data('Services')[$i]['Parameters'], 'Value', 'Name')
            : [];

        return [
            'transfer_amount'            => $transactionResponse->getAmount(),
            'transfer_paymentreference'  => $params['PaymentReference'] ?? '',
            'transfer_accountholdername' => $params['AccountHolderName'] ?? '',
            'transfer_iban'              => $params['IBAN'] ?? '',
            'transfer_bic'               => $params['BIC'] ?? '',
        ];
    }
}
