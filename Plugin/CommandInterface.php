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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as MagentoCommandInterface;

class CommandInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public BuckarooLoggerInterface $logger;

    /**
     * @var Factory
     */
    public Factory $configProviderMethodFactory;

    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @param Factory $configProviderMethodFactory
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     */
    public function __construct(
        Factory $configProviderMethodFactory,
        BuckarooLoggerInterface $logger,
        Data $helper
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Around plugin for executing authorize and order command. It will update status and state for the order.
     *
     * @param MagentoCommandInterface $commandInterface
     * @param \Closure $proceed
     * @param OrderPaymentInterface $payment
     * @param string|float|int $amount
     * @param OrderInterface $order
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
        $paymentCode = substr($methodInstance->getCode(), 0, 18);

        $this->logger->addDebug(__METHOD__ . '|1|' . var_export([$methodInstance->getCode(), $paymentAction], true));

        if ($paymentCode == 'buckaroo_magento2_' && $paymentAction) {
            if (($methodInstance->getCode() == PayPerEmail::CODE) && ($paymentAction == 'order')) {
                $config = $this->configProviderMethodFactory->get(PayPerEmail::CODE);
                if ($config->isEnabledB2B()) {
                    $this->logger->addDebug(__METHOD__ . '|5|');
                    return $message;
                }
            }
            $this->updateOrderStateAndStatus($order, $methodInstance);
        }

        return $message;
    }

    /**
     * Update order state and status based on the payment method
     *
     * @param OrderInterface|Order $order
     * @param MethodInterface $methodInstance
     * @throws Exception
     */
    private function updateOrderStateAndStatus(OrderInterface $order, MethodInterface $methodInstance)
    {
        $orderState = Order::STATE_NEW;
        $orderStatus = $this->helper->getOrderStatusByState($order, $orderState);

        $this->logger->addDebug(__METHOD__ . '|5|' . var_export($orderStatus, true));

        if ((
                (
                    preg_match('/afterpay/', $methodInstance->getCode())
                    && $this->helper->getOriginalTransactionKey($order->getIncrementId())
                )
                || (
                    preg_match('/eps/', $methodInstance->getCode())
                    && ($this->helper->getMode($methodInstance->getCode()) != Data::MODE_LIVE)
                )
            )
            && ($orderStatus == 'pending')
            && ($order->getState() === Order::STATE_PROCESSING)
            && ($order->getStatus() === Order::STATE_PROCESSING)
        ) {
            $this->logger->addDebug(__METHOD__ . '|10|');
            return false;
        }

        //skip setting the status here for applepay
        if (preg_match('/applepay/', $methodInstance->getCode())) {
            return;
        }
        $order->setState($orderState);
        $order->setStatus($orderStatus);
    }
}
