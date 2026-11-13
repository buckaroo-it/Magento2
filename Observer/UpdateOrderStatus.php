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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment;

class UpdateOrderStatus implements ObserverInterface
{
    /**
     * @var Account
     */
    protected $account;

    /**
     * @param Account $account
     */
    public function __construct(
        Account $account
    ) {
        $this->account = $account;
    }

    /**
     * Update order status by buckaroo account configuration on sales_order_payment_place_end event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var $payment Payment
         */
        $payment = $observer->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $order = $payment->getOrder();

        $newStatus = $this->account->getOrderStatusNew($order->getStore());
        $createOrderBeforeTransaction = $this->account->getCreateOrderBeforeTransaction($order->getStore());

        if ($newStatus && !$createOrderBeforeTransaction) {
            $currentStatus = $order->getStatus();

            if ($currentStatus === 'pending') {
                $allowedStatuses = ['pending_payment', 'pending_review'];

                if (in_array($newStatus, $allowedStatuses)) {
                    $order->setStatus($newStatus);
                    $order->addCommentToStatusHistory(
                        'Order status updated by Buckaroo payment placement to: ' . $newStatus,
                        $newStatus
                    );
                } else {
                    if (in_array($newStatus, ['processing', 'complete', 'canceled'])) {
                        $order->addCommentToStatusHistory(
                            'Buckaroo payment placed. Status will be updated by payment processor response.',
                        );
                    }
                }
            }
        }
    }
}
