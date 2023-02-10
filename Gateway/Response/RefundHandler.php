<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Push;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class RefundHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();
        $this->transactionResponse = SubjectReader::readTransactionResponse($response);

        $this->refundTransactionSdk($this->transactionResponse, $payment);
    }

    /**
     * Refund Transaction
     *
     * @param TransactionResponse $responseData
     * @param InfoInterface|null $payment
     * @return array|\StdClass|TransactionResponse
     */
    public function refundTransactionSdk(TransactionResponse $responseData, $payment = null)
    {
        $pendingApprovalStatus = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL');

        if (!empty($responseData->getStatusCode())
            && ($responseData->getStatusCode() == $pendingApprovalStatus)
            && $payment
            && !empty($responseData->getRelatedTransactions())
        ) {
            $this->buckarooLog->addDebug(__METHOD__ . '|10|');
            $transactionKeysArray = $payment->getAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            foreach ($responseData->getRelatedTransactions() as $relatedTransaction) {
                $transactionKeysArray[$relatedTransaction['RelatedTransactionKey']] = $responseData->getStatusCode();
            }
            $payment->setAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $transactionKeysArray
            );
            $connection = $this->resourceConnection->getConnection();
            $connection->rollBack();
            $this->messageManager->addErrorMessage(
                __("Refund has been initiated, but it needs to be approved, so you need to wait for an approval")
            );
            $payment->save();
        }

        return $responseData;
    }
}
