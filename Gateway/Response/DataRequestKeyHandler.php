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
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Transaction\Status\Response;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Handles saving the DataRequest key from the Klarna MOR Reserve response.
 * The DataRequest key replaces the Klarna reservation number in the MOR flow.
 */
class DataRequestKeyHandler implements HandlerInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var TransactionResponse $transactionResponse */
        $transactionResponse = SubjectReader::readTransactionResponse($response);

        if ($payment->getMethod() !== 'buckaroo_magento2_klarna') {
            return;
        }

        $order = $payment->getOrder();

        if ($order->getBuckarooDatarequestKey()) {
            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [%s:%s] - DataRequest key already set for order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $order->getBuckarooDatarequestKey()
            ));
            return;
        }

        $serviceParameters = $transactionResponse->getServiceParameters();
        $statusCode = $transactionResponse->getStatusCode();

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] | [%s:%s] - Processing Reserve response for order %s | '
            . 'statusCode: %s | serviceParameters: %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId(),
            $statusCode,
            json_encode($serviceParameters)
        ));

        if (isset($serviceParameters['klarna_datarequestkey'])) {
            $dataRequestKey = $serviceParameters['klarna_datarequestkey'];
            $order->setBuckarooDatarequestKey($dataRequestKey);
            $payment->setAdditionalInformation('buckaroo_datarequest_key', $dataRequestKey);
            $order->save();

            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [%s:%s] - Successfully saved DataRequest key for order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $dataRequestKey
            ));
        } elseif ($this->isPendingStatus((int)$statusCode)) {
            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [%s:%s] - Pending status %s for order %s, '
                . 'DataRequest key expected after customer completes redirect flow.',
                __METHOD__,
                __LINE__,
                $statusCode,
                $order->getIncrementId()
            ));
        } else {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] | [%s:%s] - WARNING: No DataRequest key in response for order %s! '
                . 'Status: %s | Available service parameters: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $statusCode,
                json_encode(array_keys($serviceParameters))
            ));
        }
    }

    /**
     * Check if the status code indicates a pending/redirect state where the DataRequest key is not yet available.
     *
     * @param int $statusCode
     * @return bool
     */
    private function isPendingStatus(int $statusCode): bool
    {
        return in_array($statusCode, [
            Response::STATUSCODE_WAITING_ON_USER_INPUT,
            Response::STATUSCODE_PENDING_PROCESSING,
            Response::STATUSCODE_WAITING_ON_CONSUMER,
            Response::STATUSCODE_PAYMENT_ON_HOLD,
            Response::STATUSCODE_PENDING_APPROVAL,
        ]);
    }
}
