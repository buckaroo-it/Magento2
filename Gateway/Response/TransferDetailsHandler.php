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

        /** @var SDKTransactionResponse $transaction */
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        $transferDetails = $this->getTransferDetails($transactionResponse);
        $payment->setAdditionalInformation('transfer_details', $transferDetails);
    }

    /**
     * @param        $transactionResponse
     * @return array
     */
    protected function getTransferDetails($transactionResponse): array
    {
        $serviceParameters = $transactionResponse->getServiceParameters();

        return [
            'transfer_amount'            => $transactionResponse->getAmount(),
            'transfer_paymentreference'  => $serviceParameters['paymentreference'] ?? '',
            'transfer_accountholdername' => $serviceParameters['accountholdername'] ?? '',
            'transfer_iban'              => $serviceParameters['iban'] ?? '',
            'transfer_bic'               => $serviceParameters['bic'] ?? '',
        ];
    }
}
