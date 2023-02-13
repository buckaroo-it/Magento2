<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Push;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ResourceConnection;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Framework\Message\ManagerInterface as MessageManager;

class RefundHandler implements HandlerInterface
{
    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var BuckarooLog
     */
    protected BuckarooLog $buckarooLog;

    /**
     * @var MessageManager
     */
    protected MessageManager $messageManager;


    /**
     * Constructor
     *
     * @param Data $helper
     */
    public function __construct(
        Data               $helper,
        BuckarooLog        $buckarooLog,
        ResourceConnection $resourceConnection,
        MessageManager     $messageManager
    ) {
        $this->helper = $helper;
        $this->buckarooLog = $buckarooLog;
        $this->resourceConnection = $resourceConnection;
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        $this->refundTransactionSdk($transactionResponse, $payment);
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
