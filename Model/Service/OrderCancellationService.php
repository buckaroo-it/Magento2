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

namespace Buckaroo\Magento2\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\Log;

/**
 * Service for handling order cancellations with custom stock logic
 */
class OrderCancellationService
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
     * Cancel an order with custom stock logic
     *
     * @param Order $order
     * @param string $reason
     * @param bool $isAutomatic
     * @return bool
     */
    public function cancelOrder(Order $order, string $reason = '', bool $isAutomatic = false): bool
    {
        try {
            $this->logger->addDebug(sprintf(
                '[ORDER_CANCEL_SERVICE] Canceling order %s (State: %s, Status: %s, Payment: %s, Automatic: %s)',
                $order->getIncrementId(),
                $order->getState(),
                $order->getStatus(),
                $order->getPayment() ? $order->getPayment()->getMethod() : 'No payment',
                $isAutomatic ? 'YES' : 'NO'
            ));

            if ($this->isBuckarooPendingOrder($order)) {
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_SERVICE] Intercepting cancellation for Buckaroo pending order %s',
                    $order->getIncrementId()
                ));

                $stockReserved = $this->wasStockReservedForOrder($order);
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_SERVICE] Order %s stock reserved: %s',
                    $order->getIncrementId(),
                    $stockReserved ? 'YES' : 'NO'
                ));

                if (!$stockReserved) {
                    $this->logger->addDebug(sprintf(
                        '[ORDER_CANCEL_SERVICE] Order %s never reserved stock, canceling without stock release',
                        $order->getIncrementId()
                    ));

                    $this->cancelOrderWithoutStockRelease($order, $reason, $isAutomatic);
                    return true;
                }
            } else {
                $this->logger->addDebug(sprintf(
                    '[ORDER_CANCEL_SERVICE] Order %s is not a Buckaroo pending order, using standard cancellation',
                    $order->getIncrementId()
                ));
            }

            $order->cancel();
            $order->save();

            $this->logger->addDebug(sprintf(
                '[ORDER_CANCEL_SERVICE] Order %s canceled with standard method',
                $order->getIncrementId()
            ));

            return true;
        } catch (\Throwable $e) {
            $this->logger->addDebug('[ORDER_CANCEL_SERVICE] Error in cancelOrder: ' . $e->getMessage());
            return false;
        }
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
            return true; // Assume stock was reserved if we can't check
        }
    }

    /**
     * Cancel the order without releasing stock
     *
     * @param Order $order
     * @param string $reason
     * @param bool $isAutomatic
     * @return void
     */
    private function cancelOrderWithoutStockRelease(Order $order, string $reason = '', bool $isAutomatic = false): void
    {
        try {
            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);

            $comment = $isAutomatic
                ? __('Order automatically canceled. No stock was released as this order never reserved inventory. %1', $reason)
                : __('Order canceled manually. No stock was released as this order never reserved inventory. %1', $reason);

            $order->addCommentToStatusHistory($comment, false, false);
            $order->save();

            $this->logger->addDebug(sprintf(
                '[ORDER_CANCEL_SERVICE] Order %s canceled without stock release (Automatic: %s)',
                $order->getIncrementId(),
                $isAutomatic ? 'YES' : 'NO'
            ));
        } catch (\Throwable $e) {
            $this->logger->addDebug('[ORDER_CANCEL_SERVICE] Error canceling order without stock release: ' . $e->getMessage());
        }
    }
}

