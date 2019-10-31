<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Service\Sales\Transaction;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\OrderStatusFactory;

class Cancel
{
    /** @var Account */
    private $account;

    /** @var OrderPaymentRepositoryInterface */
    private $orderPaymentRepository;

    /** @var OrderStatusFactory */
    private $orderStatusFactory;

    /**
     * @param OrderStatusFactory              $orderStatusFactory
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Account                         $account
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        Account $account
    ) {
        $this->orderStatusFactory = $orderStatusFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->account = $account;
    }

    /**
     * @param TransactionInterface|Transaction $transaction
     *
     * @throws \Exception
     * @throws LocalizedException
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
     * @param Order $order
     *
     * @throws \Exception
     * @throws LocalizedException
     */
    private function cancelOrder($order)
    {
        /** @var Payment $payment */
        $payment = $order->getPayment();
        $paymentCode = $payment->getMethodInstance()->getCode();

        if ($paymentCode == 'tig_buckaroo_afterpay' || $paymentCode == 'tig_buckaroo_afterpay2') {
            $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
            $payment->save();
        }

        $order->cancel()->save();
    }

    /**
     * @param Order $order
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

        $order->addStatusHistoryComment($comment, $newStatus);
        $order->save();
    }

    /**
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
}
