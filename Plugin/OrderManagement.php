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

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\Log;

/**
 * Plugin to prevent stock release when canceling orders that never reserved stock
 */
class OrderManagement
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Log
     */
    private $logger;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Log $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        Log $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     *
     * @param OrderManagementInterface $subject
     * @param int $orderId
     * @return array
     */
    public function beforeCancel(OrderManagementInterface $subject, $orderId)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderRepository = $objectManager->get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $order = $orderRepository->get($orderId);

            $this->logger->addDebug(sprintf(
                '[ORDER_CANCEL_PLUGIN] Checking order %s (State: %s, Status: %s, Payment: %s)',
                $order->getIncrementId(),
                $order->getState(),
                $order->getStatus(),
                $order->getPayment() ? $order->getPayment()->getMethod() : 'No payment'
            ));

            if ($this->isBuckarooPendingOrder($order)) {
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_PLUGIN] Intercepting cancellation for Buckaroo pending order %s',
                    $order->getIncrementId()
                ));

                $stockReserved = $this->wasStockReservedForOrder($order);
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_PLUGIN] Order %s stock reserved: %s',
                    $order->getIncrementId(),
                    $stockReserved ? 'YES' : 'NO'
                ));

                if (!$stockReserved) {
                    $this->logger->addDebug(sprintf(
                        '[ORDER_CANCEL_PLUGIN] Order %s never reserved stock, preventing standard cancellation',
                        $order->getIncrementId()
                    ));

                    $this->cancelOrderWithoutStockRelease($order);

                    throw new \Exception('Order canceled without stock release');
                }
            } else {
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_PLUGIN] Order %s is not a Buckaroo pending order, allowing standard cancellation',
                    $order->getIncrementId()
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->addDebug('[ORDER_CANCEL_PLUGIN] Error in beforeCancel: ' . $e->getMessage());
        }

        return [$orderId];
    }

    /**
     * Check if this is a Buckaroo order in pending state
     *
     * @param Order $order
     * @return bool
     */
    private function isBuckarooPendingOrder(Order $order): bool
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }

        $method = $payment->getMethod();
        $isBuckaroo = strpos($method, 'buckaroo_magento2_') === 0;
        $isPending = $order->getState() === Order::STATE_NEW && $order->getStatus() === 'pending';

        return $isBuckaroo && $isPending;
    }

    /**
     * Check if stock was actually reserved for this order
     *
     * @param Order $order
     * @return bool
     */
    private function wasStockReservedForOrder(Order $order): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('inventory_reservation');

            $select = $connection->select()
                ->from($table, ['cnt' => new \Zend_Db_Expr('COUNT(*)')])
                ->where('metadata LIKE ?', '%"object_id":"' . $order->getIncrementId() . '"%')
                ->limit(1);

            $count = (int)$connection->fetchOne($select);

            $this->logger->addDebug(sprintf(
                '[STOCK_CHECK] Order %s has %d inventory reservations',
                $order->getIncrementId(),
                $count
            ));

            return $count > 0;
        } catch (\Throwable $e) {
            $this->logger->addDebug('[STOCK_CHECK] Error checking stock reservations: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Cancel the order without releasing stock
     *
     * @param Order $order
     * @return void
     */
    private function cancelOrderWithoutStockRelease(Order $order): void
    {
        try {
            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);

            $order->addCommentToStatusHistory(
                __('Order canceled manually. No stock was released as this order never reserved inventory.'),
                false,
                false
            );

            $order->save();

            $this->logger->addDebug(sprintf(
                '[ORDER_CANCEL_PLUGIN] Order %s canceled without stock release',
                $order->getIncrementId()
            ));
        } catch (\Throwable $e) {
            $this->logger->addDebug('[ORDER_CANCEL_PLUGIN] Error canceling order without stock release: ' . $e->getMessage());
        }
    }
}
