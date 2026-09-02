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

namespace Buckaroo\Magento2\Service\Sales\Transaction;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as PaymentOrder;
use Magento\Sales\Model\Order\Payment\Transaction;

class Cancel
{
    /**
     * @var Account
     */
    private $account;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $orderPaymentRepository;

    /**
     * @var OrderStatusFactory
     */
    private $orderStatusFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param OrderStatusFactory $orderStatusFactory
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Account $account
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        Account $account,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->orderStatusFactory = $orderStatusFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->account = $account;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Cancel a transaction and update the order status.
     *
     * Also cancels the order if the configuration is set to cancel on failed transactions.
     *
     * @param TransactionInterface|Transaction $transaction
     *
     * @throws LocalizedException
     * @throws \Exception
     */
    public function cancel($transaction)
    {
        $this->cancelPayment($transaction);

        $order = $transaction->getOrder();

        $store = $order->getStore();
        $cancelOnFailed = $this->account->getCancelOnFailed($store);

        if ($cancelOnFailed && $order->canCancel()) {
            $this->cancelOrder($order);
        }

        $this->updateStatus($order);
    }

    /**
     * Cancels a payment associated with the given transaction.
     *
     * @param TransactionInterface|Transaction $transaction
     *
     * @throws LocalizedException
     */
    private function cancelPayment($transaction)
    {
        /** @var OrderPaymentInterface|Payment $payment */
        $payment = $this->orderPaymentRepository->get($transaction->getPaymentId());
        $payment->getMethodInstance()->cancel($payment);
    }

    /**
     * Cancels an order and sets the appropriate additional information
     *
     * @param PaymentOrder|Order $order
     *
     * @throws \Exception
     * @throws LocalizedException
     */
    private function cancelOrder($order)
    {
        /** @var PaymentOrder $payment */
        $payment = $order->getPayment();
        $paymentCode = $payment->getMethodInstance()->getCode();

        if ($paymentCode == 'buckaroo_magento2_afterpay' || $paymentCode == 'buckaroo_magento2_afterpay2') {
            $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
            $this->orderPaymentRepository->save($payment);
        }

        $order->cancel();
        $this->orderRepository->save($order);
    }

    /**
     * Updates the status of an order after cancelation, adding a history comment with the new status.
     *
     * @param PaymentOrder|Order $order
     *
     * @throws \Exception
     */
    private function updateStatus($order)
    {
        $comment = __('Payment status : Cancelled by consumer');
        $newStatus = $this->orderStatusFactory->get(890, $order);

        if ($order->getState() != Order::STATE_CANCELED) {
            $newStatus = false;
        }

        $order->addCommentToStatusHistory($comment, $newStatus);
        $this->orderRepository->save($order);
    }
}
