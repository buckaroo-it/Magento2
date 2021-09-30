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
    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @param Log $logging
     * @param Factory $configProviderMethodFactory
     */
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
        $paymentCode = substr($methodInstance->getCode(), 0, 18);

        $this->logging->addDebug(__METHOD__ . '|1|' . var_export([$methodInstance->getCode(), $paymentAction], true));

        if ($paymentCode == 'buckaroo_magento2_' && $paymentAction) {
            if (($methodInstance->getCode() == 'buckaroo_magento2_payperemail') && ($paymentAction == 'order')) {
                $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
                if ($config->getEnabledB2B()) {
                    $this->logging->addDebug(__METHOD__ . '|5|');
                    return $message;
                }
            }
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
        $orderStatus = $this->helper->getOrderStatusByState($order, $orderState);

        $this->logging->addDebug(__METHOD__ . '|5|' . var_export($orderStatus, true));

        if ((
                (
                    preg_match('/afterpay/', $methodInstance->getCode())
                    &&
                    $this->helper->getOriginalTransactionKey($order->getIncrementId())
                ) ||
                (
                    preg_match('/eps/', $methodInstance->getCode())
                    &&
                    ($this->helper->getMode($methodInstance->getCode()) != Data::MODE_LIVE)
                )
            )
            &&
            ($orderStatus == 'pending')
            &&
            ($order->getState() === Order::STATE_PROCESSING)
            &&
            ($order->getStatus() === Order::STATE_PROCESSING)
        ) {
            $this->logging->addDebug(__METHOD__ . '|10|');
            return false;
        }

        $order->setState($orderState);
        $order->setStatus($orderStatus);
    }
}
