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
namespace TIG\Buckaroo\Model\Plugin;

use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as MagentoCommandInterface;

class CommandInterface
{
    /**
     * @param MagentoCommandInterface $commandInterface
     * @param \Closure                $proceed
     * @param OrderPaymentInterface   $payment
     * @param                         $amount
     * @param OrderInterface          $order
     *
     * @return mixed
     */
    public function aroundExecute(
        MagentoCommandInterface $commandInterface,
        \Closure $proceed,
        OrderPaymentInterface $payment,
        $amount,
        OrderInterface $order
    ) {
        $message = $proceed($payment, $amount, $order);

        /** @var MethodInterface $methodInstance */
        $methodInstance = $payment->getMethodInstance();
        $paymentAction = $methodInstance->getConfigPaymentAction();
        $paymentCode = substr($methodInstance->getCode(), 0, 13);

        if ($paymentCode == 'tig_buckaroo_' && $paymentAction) {
            $this->updateOrderStateAndStatus($order, $methodInstance);
        }

        return $message;
    }

    /**
     * @param OrderInterface|Order $order
     * @param MethodInterface      $methodInstance
     */
    private function updateOrderStateAndStatus(OrderInterface $order, MethodInterface $methodInstance)
    {
        $orderState = Order::STATE_NEW;
        $orderStatus = $methodInstance->getConfigData('order_status');
        $states = $order->getConfig()->getStateStatuses($orderState);

        if (!$orderStatus || !array_key_exists($orderStatus, $states)) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
        }

        $order->setState($orderState);
        $order->setStatus($orderStatus);
    }
}
