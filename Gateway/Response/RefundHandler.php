<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Push;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class RefundHandler extends AbstractResponseHandler implements HandlerInterface
{
    public bool $closeRefundTransaction = true;

    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        if (!isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }

        $this->transactionResponse = $response['object'];

        $payment = $handlingSubject['payment']->getPayment();
        $responseData = json_decode(json_encode($this->transactionResponse->toArray()));

        $response = $this->refundTransactionSdk($responseData, $payment);

        $this->saveTransactionData($this->transactionResponse, $payment, $this->closeRefundTransaction, false);
        $this->afterRefund($payment, $response);
    }

    /**
     * @param $transaction
     *
     * @return array|\StdClass
     * @throws \Buckaroo\Magento2\Exception
     */
    public function refundTransactionSdk($responseData, $payment = null)
    {
        $pendingApprovalStatus = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL');

        if (
            !empty($responseData->Status->Code->Code)
            && ($responseData->Status->Code->Code == $pendingApprovalStatus)
            && $payment
            && !empty($responseData->RelatedTransactions->RelatedTransaction->_)
        ) {
            $this->logger2->addDebug(__METHOD__ . '|10|');
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            $buckarooTransactionKeysArray[$responseData->RelatedTransactions->RelatedTransaction->_] =
                $responseData->Status->Code->Code;
            $payment->setAdditionalInformation(
                Push::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $buckarooTransactionKeysArray
            );
            $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $connection->rollBack();
            $messageManager = $this->objectManager->get('Magento\Framework\Message\ManagerInterface');
            $messageManager->addError(
                __("Refund has been initiated, but it needs to be approved, so you need to wait for an approval")
            );
            $payment->save();
        }

        return $responseData;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass                                             $response
     *
     * @return $this
     */
    protected function afterRefund($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_refund_after', $payment, $response);
    }

}

