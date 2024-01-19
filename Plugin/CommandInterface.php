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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay2;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Eps;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\CommandInterface as MagentoCommandInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;

    /**
     * @param Factory $configProviderMethodFactory
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     * @param LockManagerWrapper $lockManager
     */
    public function __construct(
        Factory $configProviderMethodFactory,
        BuckarooLoggerInterface $logger,
        Data $helper,
        LockManagerWrapper $lockManager
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->lockManager = $lockManager;
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
     * @throws Exception
     */
    public function aroundExecute(
        MagentoCommandInterface $commandInterface,
        \Closure $proceed,
        OrderPaymentInterface $payment,
        $amount,
        OrderInterface $order
    ) {
        $message = $proceed($payment, $amount, $order);

        $orderIncrementID = $order->getIncrementId();
        $this->logger->addDebug(__METHOD__ . '|Lock Name| - ' . var_export($orderIncrementID, true));
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

        if (!$lockAcquired) {
            $this->logger->addError(__METHOD__ . '|lock not acquired|');
            return $message;
        }

        try {
            /** @var MethodInterface $methodInstance */
            $methodInstance = $payment->getMethodInstance();
            $paymentAction = $methodInstance->getConfigPaymentAction();
        $paymentCode = $methodInstance->getCode();
        $buckarooPaymentCode = substr($paymentCode, 0, 18);

        $this->logger->addDebug(sprintf(
            '[UPDATE_STATUS] | [Plugin] | [%s:%s] - Update order state and status |' .
            ' paymentMethod: %s | paymentAction: %s',
            __METHOD__,
            __LINE__,
            $paymentCode,
            $paymentAction
        ));

        if ($buckarooPaymentCode == 'buckaroo_magento2_' && $paymentAction && $order->canInvoice()) {
            $orderState = Order::STATE_NEW;
            $orderStatus = $this->helper->getOrderStatusByState($order, $orderState);

            if ($this->skipUpdateOrderStateAndStatus($orderStatus, $order, $methodInstance)) {
                $this->logger->addDebug(sprintf(
                    '[UPDATE_STATUS] | [Plugin] | [%s:%s] - Skip Update order state and status |' .
                    ' paymentMethod: %s | paymentAction: %s, orderStatus: %s',
                    __METHOD__,
                    __LINE__,
                    $paymentCode,
                    $paymentAction,
                    $orderStatus
                ));
                return $message;
            }

            $order->setState($orderState);
            $order->setStatus($orderStatus);
            }

            return $message;

        } catch (\Exception $e) {
            $this->logger->addDebug(__METHOD__ . '|Exception|' . $e->getMessage());
            throw $e;
        } finally {
            // Ensure the lock is released
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logger->addDebug(__METHOD__ . '|Lock released|');
        }
    }

    /**
     * Determines if the order's state and status update should be skipped based on payment method and configuration.
     *
     *  - Skips for PayPerEmail B2B when the payment action is 'order'.
     *  - Skips for Afterpay, Afterpay20, Afterpay2, and EPS if status is pending, state is processing
     *  - Always skips for Apple Pay.
     *
     * @param string $orderStatus
     * @param OrderInterface $order
     * @param MethodInterface $methodInstance
     * @return bool
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function skipUpdateOrderStateAndStatus(
        string $orderStatus,
        OrderInterface $order,
        MethodInterface $methodInstance
    ): bool {
        $paymentAction = $methodInstance->getConfigPaymentAction();
        $paymentCode = $methodInstance->getCode();

        // Skip setting the status here for PayPerEmail B2B
        if (($paymentCode == PayPerEmail::CODE) && ($paymentAction == 'order')) {
            $config = $this->configProviderMethodFactory->get(PayPerEmail::CODE);
            if ($config->isEnabledB2B()) {
                return true;
            }
        }

        // Skip setting the status here for Afterpay and EPS
        if ((
                (
                    in_array($paymentCode, [Afterpay::CODE, Afterpay20::CODE, Afterpay2::CODE])
                    && $this->helper->getOriginalTransactionKey($order->getIncrementId())
                )
                || (
                    $paymentCode == Eps::CODE
                    && ($this->helper->getMode($methodInstance->getCode()) != Data::MODE_LIVE)
                )
            )
            && ($orderStatus == 'pending')
            && ($order->getState() === Order::STATE_PROCESSING)
            && ($order->getStatus() === Order::STATE_PROCESSING)
        ) {
            return true;
        }

        // Skip setting the status here for Apple Pay
        if ($paymentCode == Applepay::CODE) {
            return true;
        }

        return false;
    }
}
