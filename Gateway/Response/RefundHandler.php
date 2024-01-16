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
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\Push;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;

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
     * @param Data $helper
     * @param BuckarooLog $buckarooLog
     * @param ResourceConnection $resourceConnection
     * @param MessageManager $messageManager
     */
    public function __construct(
        Data $helper,
        BuckarooLog $buckarooLog,
        ResourceConnection $resourceConnection,
        MessageManager $messageManager
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
     * @return TransactionResponse
     */
    public function refundTransactionSdk(
        TransactionResponse $responseData,
        InfoInterface $payment = null
    ): TransactionResponse {
        $pendingApprovalStatus = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL');

        if (!empty($responseData->getStatusCode())
            && ($responseData->getStatusCode() == $pendingApprovalStatus)
            && $payment
            && !empty($responseData->getRelatedTransactions())
        ) {
            $this->buckarooLog->addDebug(__METHOD__ . '|10|');
            $transactionKeysArray = $payment->getAdditionalInformation(
                DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            foreach ($responseData->getRelatedTransactions() as $relatedTransaction) {
                $transactionKeysArray[$relatedTransaction['RelatedTransactionKey']] = $responseData->getStatusCode();
            }
            $payment->setAdditionalInformation(
                DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
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
