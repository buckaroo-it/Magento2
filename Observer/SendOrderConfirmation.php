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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\Log;

class SendOrderConfirmation implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $accountConfig;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account          $accountConfig
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Buckaroo\Magento2\Logging\Log $logging
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        Log $logging
    ) {
        $this->accountConfig    = $accountConfig;
        $this->orderSender      = $orderSender;
        $this->logging = $logging;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var $payment \Magento\Sales\Model\Order\Payment
         */
        $payment = $observer->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        $order = $payment->getOrder();
        $order->save();

        $methodInstance = $payment->getMethodInstance();
        $sendOrderConfirmationEmail = $this->accountConfig->getOrderConfirmationEmail($order->getStore())
            || $methodInstance->getConfigData('order_email', $order->getStoreId());

        $createOrderBeforeTransaction = $this->accountConfig->getCreateOrderBeforeTransaction($order->getStore());

        /**
         * @noinspection PhpUndefinedFieldInspection
         */
        if (!$methodInstance->usesRedirect
            && !$order->getEmailSent()
            && $sendOrderConfirmationEmail
            && $order->getIncrementId()
            && !$createOrderBeforeTransaction
        ) {
            $this->logging->addDebug(__METHOD__ . '|sendemail|');
            $this->orderSender->send($order, true);
        }
    }
}
