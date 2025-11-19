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

namespace Buckaroo\Magento2\Service\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Service to properly uncancel/reactivate orders
 */
class Uncancel
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $isInventorySalesApiEnabled;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $eventManager
     * @param Manager $moduleManager
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $eventManager,
        Manager $moduleManager,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->eventManager = $eventManager;
        $this->moduleManager = $moduleManager;
        $this->logger = $logger;
    }

    /**
     * Execute order uncancellation
     *
     * @param OrderInterface $order
     * @param string|null $comment
     * @return void
     */
    public function execute(OrderInterface $order, ?string $comment = null): void
    {
        $this->isInventorySalesApiEnabled = $this->moduleManager->isEnabled('Magento_InventorySalesApi');

        $this->logger->addDebug(sprintf(
            '[UNCANCEL_ORDER] | [Service] | [%s:%s] - Uncanceling order: %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId()
        ));

        $this->updateOrder($order, $comment);
        $this->updateOrderItems($order);

        $this->orderRepository->save($order);
        $this->eventManager->dispatch('buckaroo_order_uncancel', ['order' => $order]);

        $this->logger->addDebug(sprintf(
            '[UNCANCEL_ORDER] | [Service] | [%s:%s] - Successfully uncanceled order: %s',
            __METHOD__,
            __LINE__,
            $order->getIncrementId()
        ));
    }

    /**
     * Update order state and reset all canceled amounts
     *
     * @param OrderInterface $order
     * @param string|null $comment
     * @return void
     */
    private function updateOrder(OrderInterface $order, ?string $comment = null): void
    {
        $order->setState(Order::STATE_NEW);

        $statusComment = $comment ?: __('Order reactivated: Payment completed after cancellation.');
        $order->addStatusToHistory('pending', $statusComment, false);

        // Reset all canceled amounts at order level
        $order->setSubtotalCanceled(0);
        $order->setBaseSubtotalCanceled(0);

        $order->setTaxCanceled(0);
        $order->setBaseTaxCanceled(0);

        $order->setShippingCanceled(0);
        $order->setBaseShippingCanceled(0);

        $order->setDiscountCanceled(0);
        $order->setBaseDiscountCanceled(0);

        $order->setTotalCanceled(0);
        $order->setBaseTotalCanceled(0);
    }

    /**
     * Update order items and reset all canceled quantities
     *
     * @param OrderInterface $order
     * @return void
     */
    private function updateOrderItems(OrderInterface $order): void
    {
        /** @var OrderItemInterface $item */
        foreach ($order->getAllItems() as $item) {
            if ($this->isInventorySalesApiEnabled) {
                $this->restoreInventoryReservation($item);
            }

            $this->uncancelItem($item);
        }
    }

    /**
     * Restore inventory reservation for MSI
     *
     * @param OrderItemInterface $item
     * @return void
     */
    private function restoreInventoryReservation(OrderItemInterface $item): void
    {
        try {
            // For MSI (Multi-Source Inventory), we need to restore the reservation
            // This is handled through events that MSI modules listen to
            $this->eventManager->dispatch('sales_order_item_uncancel', ['item' => $item]);
        } catch (\Exception $e) {
            $this->logger->addDebug(sprintf(
                '[UNCANCEL_ORDER] | [Service] | [%s:%s] - Failed to restore inventory for item %s: %s',
                __METHOD__,
                __LINE__,
                $item->getSku(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Reset item canceled quantities and amounts
     *
     * @param OrderItemInterface $item
     * @return void
     */
    private function uncancelItem(OrderItemInterface $item): void
    {
        $item->setQtyCanceled(0);
        $item->setTaxCanceled(0);
        $item->setDiscountTaxCompensationCanceled(0);

        $this->eventManager->dispatch('buckaroo_order_item_uncancel', ['item' => $item]);

        // Handle child items (e.g., configurable product children, bundle items)
        /** @var OrderItemInterface $child */
        foreach ($item->getChildrenItems() as $child) {
            $child->setQtyCanceled(0);
            $child->setTaxCanceled(0);
            $child->setDiscountTaxCompensationCanceled(0);

            $this->eventManager->dispatch('buckaroo_order_item_uncancel', ['item' => $child]);
        }
    }
}

