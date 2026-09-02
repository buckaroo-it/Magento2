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

namespace Buckaroo\Magento2\Model\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Service to extend a Klarna MOR reservation expiration.
 * Used when delivery is delayed and the default 28-day authorization window needs to be extended.
 */
class ExtendReservation
{
    /**
     * @var CommandManagerInterface
     */
    private CommandManagerInterface $commandManager;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @param CommandManagerInterface  $commandManager
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param BuckarooLoggerInterface  $logger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        CommandManagerInterface $commandManager,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        BuckarooLoggerInterface $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->commandManager           = $commandManager;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger                   = $logger;
        $this->orderRepository          = $orderRepository;
    }

    /**
     * Check whether the order holds an active Klarna MOR reservation that can be extended.
     *
     * @param Order $order
     *
     * @return bool
     */
    public function canExtend(Order $order): bool
    {
        $payment = $order->getPayment();

        if ($payment === null || $payment->getMethod() !== Klarna::CODE) {
            return false;
        }

        if ($payment->getAdditionalInformation('voided_by_buckaroo')) {
            return false;
        }

        if (in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_CLOSED], true)) {
            return false;
        }

        return !empty($order->getBuckarooDatarequestKey())
            || !empty($payment->getAdditionalInformation('buckaroo_datarequest_key'));
    }

    /**
     * Execute ExtendReservation data request for a Klarna MOR order.
     *
     * @param Order $order
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    public function execute(Order $order): bool
    {
        $payment = $order->getPayment();

        if ($payment->getMethod() !== Klarna::CODE) {
            return false;
        }

        $dataRequestKey = $order->getBuckarooDatarequestKey()
            ?? $payment->getAdditionalInformation('buckaroo_datarequest_key');

        if (!$dataRequestKey) {
            throw new LocalizedException(__(
                'Cannot extend Klarna MOR reservation for order %1: DataRequest key is missing.',
                $order->getIncrementId()
            ));
        }

        $this->logger->addDebug(sprintf(
            '[KLARNA_MOR] Executing ExtendReservation for order %s, DataRequestKey: %s',
            $order->getIncrementId(),
            $dataRequestKey
        ));

        try {
            $commandSubject = [
                'payment' => $this->paymentDataObjectFactory->create($payment),
                'amount'  => $order->getGrandTotal(),
            ];

            $this->commandManager->executeByCode('extend_reservation', $payment, $commandSubject);

            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] ExtendReservation succeeded for order %s',
                $order->getIncrementId()
            ));

            $this->addHistoryComment(
                $order,
                __('Buckaroo: Klarna reservation extended at payment provider.')
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] ExtendReservation failed for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));

            $this->addHistoryComment(
                $order,
                __('Buckaroo: failed to extend Klarna reservation. %1', $e->getMessage())
            );

            throw new LocalizedException(__($e->getMessage()), $e);
        }
    }

    /**
     * Add a status history comment to the order and persist it.
     *
     * @param Order $order
     * @param Phrase $comment
     *
     * @return void
     */
    private function addHistoryComment(Order $order, Phrase $comment): void
    {
        try {
            $order->addCommentToStatusHistory((string)$comment);
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] Could not save history comment for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
