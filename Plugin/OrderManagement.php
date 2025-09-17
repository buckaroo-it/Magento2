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

use Magento\Sales\Api\OrderManagementInterface;
use Buckaroo\Magento2\Model\Service\OrderCancellationService;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Plugin to prevent stock release when canceling orders that never reserved stock
 */
class OrderManagement
{
    /**
     * @var OrderCancellationService
     */
    private $orderCancellationService;

    /**
     * @var Log
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param OrderCancellationService $orderCancellationService
     * @param Log $logger
     * @param ResourceConnection $resourceConnection
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderCancellationService $orderCancellationService,
        Log $logger,
        ResourceConnection $resourceConnection,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderCancellationService = $orderCancellationService;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
        $this->orderRepository = $orderRepository;
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
            $order = $this->orderRepository->get($orderId);

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

                    $this->orderCancellationService->cancelOrder($order, 'Manual cancellation from admin panel.', false);

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
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function isBuckarooPendingOrder($order): bool
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }

        $method = $payment->getMethod();
        $isBuckaroo = strpos($method, 'buckaroo_magento2_') === 0;
        $isPending = $order->getState() === \Magento\Sales\Model\Order::STATE_NEW && $order->getStatus() === 'pending';

        return $isBuckaroo && $isPending;
    }

    /**
     * Check if stock was actually reserved for this order
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function wasStockReservedForOrder($order): bool
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
}
