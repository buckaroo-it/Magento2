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
    public bool $closeRefundTransaction = true;

    public function handle(array $handlingSubject, array $response)
    {
        $this->transactionResponse = SubjectReader::readTransactionResponse($response);

        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $response = $this->refundTransactionSdk($this->transactionResponse, $payment);

        $this->saveTransactionData($this->transactionResponse, $payment, $this->closeRefundTransaction, false);
        $this->afterRefund($payment, $response);
    }

    /**
     * @param TransactionResponse $responseData
     * @param null $payment
     * @return array|\StdClass|TransactionResponse
     */
    public function refundTransactionSdk(TransactionResponse $responseData, $payment = null)
    {
        $pendingApprovalStatus = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL');

        if (
            !empty($responseData->getStatusCode())
            && ($responseData->getStatusCode() == $pendingApprovalStatus)
            && $payment
            && !empty($responseData->getRelatedTransactions())
        ) {
            $this->buckarooLog->addDebug(__METHOD__ . '|10|');
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            foreach ($responseData->getRelatedTransactions() as $relatedTransaction) {
                $buckarooTransactionKeysArray[$relatedTransaction['RelatedTransactionKey']] = $responseData->getStatusCode();
            }
            $payment->setAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $buckarooTransactionKeysArray
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

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass $response
     *
     * @return $this
     */
    protected function afterRefund($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_refund_after', $payment, $response);
    }
}
