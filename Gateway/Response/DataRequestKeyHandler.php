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
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Transaction\Status\Response;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

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
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @param BuckarooLoggerInterface  $logger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(BuckarooLoggerInterface $logger, OrderRepositoryInterface $orderRepository)
    {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
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
            $this->orderRepository->save($order);

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
