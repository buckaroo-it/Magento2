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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender as MagentoOrderSender;

class OrderSender
{
    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Prevent Magento core from sending order emails for Buckaroo redirect payment methods
     * during order placement, since payment hasn't been completed yet.
     * Allow emails after successful payment completion.
     *
     * @param  MagentoOrderSender $subject
     * @param  \Closure           $proceed
     * @param  Order              $order
     * @param  bool               $forceSyncMode
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSend(
        MagentoOrderSender $subject,
        \Closure $proceed,
        Order $order,
        bool $forceSyncMode = false
    ): bool {
        $payment = $order->getPayment();

        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return $proceed($order, $forceSyncMode);
        }

        $methodInstance = $payment->getMethodInstance();

        if (isset($methodInstance->usesRedirect) && $methodInstance->usesRedirect) {
            $isOrderSuccessful = in_array($order->getState(), [
                Order::STATE_PROCESSING,
                Order::STATE_COMPLETE
            ]);

            if ($isOrderSuccessful) {
                $this->logger->addDebug(sprintf(
                    '[SEND_MAIL] | [Plugin] | [%s:%s] - Allow order email for successful redirect payment | order: %s | state: %s | method: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getId(),
                    $order->getState(),
                    $payment->getMethod()
                ));
                return $proceed($order, $forceSyncMode);
            }

            $this->logger->addDebug(sprintf(
                '[SEND_MAIL] | [Plugin] | [%s:%s] - Prevent Magento core order email for redirect payment method (payment not completed) | order: %s | state: %s | method: %s',
                __METHOD__,
                __LINE__,
                $order->getId(),
                $order->getState(),
                $payment->getMethod()
            ));

            return true;
        }

        return $proceed($order, $forceSyncMode);
    }
}
