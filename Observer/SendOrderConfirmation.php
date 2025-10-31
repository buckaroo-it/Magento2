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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;

class SendOrderConfirmation implements ObserverInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    public $logger;
    /**
     * @var Account
     */
    protected $accountConfig;
    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @param Account                 $accountConfig
     * @param OrderSender             $orderSender
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Account $accountConfig,
        OrderSender $orderSender,
        BuckarooLoggerInterface $logger
    ) {
        $this->accountConfig = $accountConfig;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
    }

    /**
     * Send order confirmation on email using sales_order_payment_place_end event
     *
     * @param  Observer           $observer
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        /**
         * @var Payment $payment
         */
        $payment = $observer->getPayment();

        if (!$this->isBuckarooPayment($payment)) {
            return;
        }

        $order = $payment->getOrder();
        $methodInstance = $payment->getMethodInstance();

        if ($this->shouldSkipForRedirectMethod($methodInstance, $order, $payment)) {
            return;
        }

        $this->processOrderEmailSending($order, $methodInstance);
    }

    /**
     * Check if the payment method is a Buckaroo payment
     *
     * @param  Payment $payment
     * @return bool
     */
    private function isBuckarooPayment(Payment $payment): bool
    {
        return strpos($payment->getMethod(), 'buckaroo_magento2') !== false;
    }

    /**
     * Check if should skip email sending for redirect payment method
     *
     * @param  mixed   $methodInstance
     * @param  mixed   $order
     * @param  Payment $payment
     * @return bool
     */
    private function shouldSkipForRedirectMethod($methodInstance, $order, Payment $payment): bool
    {
        /**
         * @noinspection PhpUndefinedFieldInspection
         */
        if ($methodInstance->usesRedirect) {
            $this->logger->addDebug(sprintf(
                '[SEND_MAIL] | [Observer] | [%s:%s] - Skip sending order confirmation email - redirect payment method | order: %s | method: %s',
                __METHOD__,
                __LINE__,
                $order->getId(),
                $payment->getMethod()
            ));
            return true;
        }
        return false;
    }

    /**
     * Process order email sending logic
     *
     * @param mixed $order
     * @param mixed $methodInstance
     */
    private function processOrderEmailSending($order, $methodInstance): void
    {
        $sendOrderConfirmationEmail = $this->accountConfig->getOrderConfirmationEmail($order->getStore())
            || $methodInstance->getConfigData('order_email', $order->getStoreId());

        $createOrderBeforeTransaction = $this->accountConfig->getCreateOrderBeforeTransaction($order->getStore());

        if ($this->shouldSendOrderEmail($order, $sendOrderConfirmationEmail, $createOrderBeforeTransaction)) {
            $this->logger->addDebug(sprintf(
                '[SEND_MAIL] | [Observer] | [%s:%s] - Send order confirmation on email | order: %s',
                __METHOD__,
                __LINE__,
                $order->getId()
            ));
            $this->orderSender->send($order, true);
        } else {
            $this->logger->addDebug(sprintf(
                '[SEND_MAIL] | [Observer] | [%s:%s] - Skip sending order confirmation email | order: %s | emailSent: %s | confirmationEmail: %s | createOrderBefore: %s',
                __METHOD__,
                __LINE__,
                $order->getId(),
                $order->getEmailSent() ? 'Yes' : 'No',
                $sendOrderConfirmationEmail ? 'Yes' : 'No',
                $createOrderBeforeTransaction ? 'Yes' : 'No'
            ));
        }
    }

    /**
     * Check if order email should be sent
     *
     * @param  mixed $order
     * @param  bool  $sendOrderConfirmationEmail
     * @param  bool  $createOrderBeforeTransaction
     * @return bool
     */
    private function shouldSendOrderEmail($order, bool $sendOrderConfirmationEmail, bool $createOrderBeforeTransaction): bool
    {
        return !$order->getEmailSent()
            && $sendOrderConfirmationEmail
            && $order->getIncrementId()
            && !$createOrderBeforeTransaction;
    }
}
