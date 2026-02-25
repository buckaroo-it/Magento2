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

namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * Defers order confirmation email for Buckaroo redirect payment methods until
 * payment is confirmed (Push or return to Redirect Process). Prevents the email
 * from being sent when the customer is only redirected to the payment page.
 */
class OrderSenderPlugin
{
    public  const DEFER_FLAG_KEY = 'buckaroo_defer_order_confirmation_email';

    /**
     * Skip sending order confirmation email when the order is a Buckaroo redirect
     * method and the email is still deferred (payment not yet confirmed).
     *
     * @param OrderSender $subject
     * @param callable $proceed
     * @param Order $order
     * @param bool $forceSyncMode
     * @return bool
     */
    public function aroundSend(OrderSender $subject, callable $proceed, $order, $forceSyncMode = false)
    {
        $payment = $order->getPayment();
        if (!$payment || strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return $proceed($order, $forceSyncMode);
        }

        $isDeferred = $payment->getAdditionalInformation(self::DEFER_FLAG_KEY);
        if ($isDeferred && $order->getState() === Order::STATE_NEW) {
            return false;
        }

        return $proceed($order, $forceSyncMode);
    }
}
