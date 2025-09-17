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

namespace Buckaroo\Magento2\Service\Sales\Transaction;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as PaymentOrder;
use Magento\Sales\Model\Order\Payment\Transaction;

class Cancel
{
    /**
     * @var Account
     */
    private Account $account;

    /** @var OrderCancellationService */
    private $orderCancellationService;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $orderPaymentRepository;

    /**
     * @var OrderStatusFactory
     */
    private OrderStatusFactory $orderStatusFactory;

    /**
     * @param OrderStatusFactory $orderStatusFactory
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Account $account
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        Account $account,
        OrderCancellationService $orderCancellationService
    ) {
        $this->orderStatusFactory = $orderStatusFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->account = $account;
        $this->orderCancellationService = $orderCancellationService;
    }

    /**
     * Cancels a transaction, updates the order status, and cancels the order
     * if the configuration is set to cancel on failed transactions.
     *
     * @param TransactionInterface|Transaction $transaction
     * @return void
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
            $payment->save();
        }

        $this->orderCancellationService->cancelOrder($order, 'Cancelled by consumer.', true);
    }

    /**
     * Updates the status of an order after cancelation, adding a history comment with the new status.
     *
     * @param PaymentOrder|Order $order
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
        $order->save();
    }
}
