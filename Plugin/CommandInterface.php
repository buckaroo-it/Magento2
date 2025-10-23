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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as MagentoCommandInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Method\PayPerEmail;

class CommandInterface
{
    /** @var Log */
    public $logging;

    /** @var Factory */
    public $configProviderMethodFactory;

    /** @var Data */
    public $helper;

    public function __construct(
        Factory $configProviderMethodFactory,
        Log $logging,
        Data $helper
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->logging = $logging;
        $this->helper = $helper;
    }

    /**
     * Around plugin for execute().
     *
     * @param  MagentoCommandInterface $commandInterface
     * @param  \Closure                $proceed
     * @param  OrderPaymentInterface   $payment
     * @param  mixed                   $amount
     * @param  OrderInterface          $order
     * @throws \Exception
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

        try {
            /** @var MethodInterface $methodInstance */
            $methodInstance = $payment->getMethodInstance();
            $paymentAction = $methodInstance->getConfigPaymentAction();
            $paymentCode = substr($methodInstance->getCode(), 0, 18);

            $this->logging->addDebug(
                __METHOD__ . '|Method & Action: ' . var_export([$methodInstance->getCode(), $paymentAction], true)
            );

            // Only act on Buckaroo methods with a configured payment action.
            if ($paymentCode === 'buckaroo_magento2_' && $paymentAction) {
                // Special handling for PayPerEmail B2B in order mode.
                if ($methodInstance->getCode() === 'buckaroo_magento2_payperemail' && $paymentAction === 'order') {
                    $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
                    if ($config->getEnabledB2B()) {
                        $this->logging->addDebug(__METHOD__ . '|Skipping update for PPE B2B.');
                        return $message;
                    }
                }
                $this->updateOrderStateAndStatus($order, $methodInstance);
            }
            return $message;
        } catch (\Exception $e) {
            $this->logging->addDebug(__METHOD__ . '|Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update order state and status if necessary.
     *
     * @param OrderInterface $order
     * @param MethodInterface $methodInstance
     * @return bool|void
     * @throws Exception
     */
    private function updateOrderStateAndStatus(OrderInterface $order, MethodInterface $methodInstance)
    {
        $targetState = Order::STATE_NEW;
        $defaultStatus = $this->helper->getOrderStatusByState($order, $targetState);
        $currentStatus = $order->getStatus();

        $this->logging->addDebug(__METHOD__ . '|Default Status: ' . $defaultStatus);
        $this->logging->addDebug(__METHOD__ . '|Current Status: ' . $currentStatus);

        // Special condition for afterpay/eps
        if ($this->isAfterpayOrEps($methodInstance, $order)) {
            if ($defaultStatus === 'pending' &&
                $order->getState() === Order::STATE_PROCESSING &&
                $order->getStatus() === Order::STATE_PROCESSING) {
                $this->logging->addDebug(__METHOD__ . '|Afterpay/eps condition met, skipping update.');
                return false;
            }
        }

        // Skip update for Apple Pay
        if (preg_match('/applepay/', $methodInstance->getCode())) {
            $this->logging->addDebug(__METHOD__ . '|ApplePay detected, skipping update.');
            return;
        }

        // Update order state only if it is not already the target state.
        if ($order->getState() !== $targetState) {
            $this->logging->addDebug(__METHOD__ . '|Updating order state from ' . $order->getState() . ' to ' . $targetState);
            $order->setState($targetState);
        } else {
            $this->logging->addDebug(__METHOD__ . '|Order state is already ' . $targetState);
        }

        // Update status only if the order’s current status is either empty or equals the default.
        if (empty($currentStatus) || $currentStatus === $defaultStatus) {
            $this->logging->addDebug(__METHOD__ . '|Updating order status to default: ' . $defaultStatus);
            $order->setStatus($defaultStatus);
        } else {
            $this->logging->addDebug(__METHOD__ . '|Custom status detected, preserving: ' . $currentStatus);
        }
    }

    /**
     * Helper to check if the payment method is afterpay or eps in conditions that should skip updates.
     *
     * @param  MethodInterface $methodInstance
     * @param  OrderInterface  $order
     * @throws Exception
     * @return bool
     */
    private function isAfterpayOrEps(MethodInterface $methodInstance, OrderInterface $order): bool
    {
        if (preg_match('/afterpay/', $methodInstance->getCode())) {
            return (bool) $this->helper->getOriginalTransactionKey($order->getIncrementId());
        }
        if (preg_match('/eps/', $methodInstance->getCode())) {
            return ($this->helper->getMode($methodInstance->getCode()) != Data::MODE_LIVE);
        }
        return false;
    }
}
