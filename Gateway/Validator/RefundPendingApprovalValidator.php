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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Model\Transaction\Status\Response;
use Buckaroo\Magento2\Model\ConfigProvider\Refund as RefundConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class RefundPendingApprovalValidator extends AbstractValidator
{
    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var RefundConfigProvider
     */
    protected RefundConfigProvider $refundConfigProvider;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param ResultInterfaceFactory $resultFactory
     * @param RefundConfigProvider $refundConfigProvider
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        ResultInterfaceFactory $resultFactory,
        RefundConfigProvider $refundConfigProvider
    ) {
        parent::__construct($resultFactory);
        $this->logger = $logger;
        $this->refundConfigProvider = $refundConfigProvider;
    }

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @throws LocalizedException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = SubjectReader::readPayment($validationSubject)->getPayment();
        $transactionResponse = SubjectReader::readTransactionResponse($validationSubject['response']);
        $statusCode = $transactionResponse->getStatusCode();

        if (!empty($statusCode)
            && ($statusCode == Response::STATUSCODE_PENDING_APPROVAL)
            && $payment
            && !empty($transactionResponse->getRelatedTransactions())
        ) {
            $this->logger->addDebug(sprintf(
                '[REFUND] | [RESPONSE] | [%s:%s] - Pending Approval validator',
                __METHOD__,
                __LINE__,
            ));

            $transactionKeysArray = $payment->getAdditionalInformation(
                DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            foreach ($transactionResponse->getRelatedTransactions() as $relatedTransaction) {
                $transactionKeysArray[$relatedTransaction['RelatedTransactionKey']] =
                    $statusCode;
            }
            $payment->setAdditionalInformation(
                DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES,
                $transactionKeysArray
            );

            if ($this->refundConfigProvider->getPendingApprovalSetting() == RefundConfigProvider::PENDING_REFUND_ON_APPROVE) {
                $response = false;
            } else {
                $response = true;
                $payment->setAdditionalInformation(
                    RefundConfigProvider::ADDITIONAL_INFO_PENDING_REFUND_STATUS,
                    $statusCode
                );
            }

            $payment->save();

            return $this->createResult(
                $response,
                [__('Refund has been initiated, but it needs to be approved, so you need to wait for an approval')],
                [$statusCode]
            );
        }

        return $this->createResult(true, [__('Transaction Success')], [$statusCode]);
    }
}
