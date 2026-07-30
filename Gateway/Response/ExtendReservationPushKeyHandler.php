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
use Buckaroo\Magento2\Service\Push\KlarnaMorOrderService;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;

/**
 * Registers the ExtendReservation DataRequest key so the async push can be matched to the order.
 */
class ExtendReservationPushKeyHandler implements HandlerInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;

    /**
     * @param BuckarooLoggerInterface         $logger
     * @param OrderPaymentRepositoryInterface $paymentRepository
     */
    public function __construct(BuckarooLoggerInterface $logger, OrderPaymentRepositoryInterface $paymentRepository)
    {
        $this->logger = $logger;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var TransactionResponse $transactionResponse */
        $transactionResponse = SubjectReader::readTransactionResponse($response);
        $transactionKey = $transactionResponse->getTransactionKey();

        if (empty($transactionKey)) {
            return;
        }

        $pendingKeys = (array)$payment->getAdditionalInformation(KlarnaMorOrderService::PENDING_DATAREQUEST_PUSH_KEYS);
        $pendingKeys[$transactionKey] = true;
        $payment->setAdditionalInformation(KlarnaMorOrderService::PENDING_DATAREQUEST_PUSH_KEYS, $pendingKeys);

        if (!empty($payment->getOrder()) && !empty($payment->getOrder()->getId())) {
            $this->paymentRepository->save($payment);
        }

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] Registered pending ExtendReservation push key %s for order %s',
            $transactionKey,
            $payment->getOrder() ? $payment->getOrder()->getIncrementId() : 'unknown'
        ));
    }
}
