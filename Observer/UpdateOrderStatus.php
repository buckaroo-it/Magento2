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
     * @return void
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
