<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Service\Order;

use Magento\Framework\App\ObjectManager;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Service to properly uncancel/reactivate orders
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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
        // What the cancel gave back to stock, read before the items are reset
        $released = $this->getReleasedQuantities($order);

        foreach ($order->getAllItems() as $item) {
            $this->uncancelItem($item);
        }

        if ($released !== []) {
            $this->reserveStockAgain($order, $released);
        }
    }

    /**
     * Quantities the cancel returned to stock, per simple product: [sku => [productId, qty]]
     *
     * @param OrderInterface $order
     *
     * @return array
     */
    private function getReleasedQuantities(OrderInterface $order): array
    {
        $released = [];

        foreach ($order->getAllItems() as $item) {
            // Stock is kept on the simple products: skip parents (configurable, bundle) that have children
            if (!empty($item->getChildrenItems()) || (float)$item->getQtyCanceled() <= 0) {
                continue;
            }

            $sku = (string)$item->getSku();
            $released[$sku] = [
                (int)$item->getProductId(),
                ($released[$sku][1] ?? 0) + (float)$item->getQtyCanceled(),
                (string)$item->getProductType(),
            ];
        }

        return $released;
    }

    /**
     * Take the stock the cancel released again, as placing the order did
     *
     * @param OrderInterface $order
     * @param array          $released
     *
     * @return void
     */
    private function reserveStockAgain(OrderInterface $order, array $released): void
    {
        try {
            if ($this->isInventorySalesApiEnabled) {
                $this->placeInventoryReservations($order, $released);
            } else {
                $this->registerLegacyStockSale($order, $released);
            }
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[UNCANCEL_ORDER] | [Service] | [%s:%s] - Could not reserve stock again for order %s: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Multi-Source Inventory: a reservation per product, like the one made when the order was placed
     *
     * @param OrderInterface $order
     * @param array          $released
     *
     * @return void
     */
    private function placeInventoryReservations(OrderInterface $order, array $released): void
    {
        $objectManager = ObjectManager::getInstance();
        $isManaged = $objectManager->get(
            \Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface::class
        );

        $items = [];
        foreach ($released as $sku => [, $qty, $productType]) {
            if ($productType !== '' && !$isManaged->execute($productType)) {
                continue;
            }
            $items[] = $objectManager->create(
                \Magento\InventorySalesApi\Api\Data\ItemToSellInterface::class,
                ['sku' => (string)$sku, 'qty' => -(float)$qty]
            );
        }

        if ($items === []) {
            return;
        }

        $salesChannel = $objectManager->create(
            \Magento\InventorySalesApi\Api\Data\SalesChannelInterface::class,
            ['data' => [
                'type' => \Magento\InventorySalesApi\Api\Data\SalesChannelInterface::TYPE_WEBSITE,
                'code' => $order->getStore()->getWebsite()->getCode(),
            ]]
        );
        $salesEvent = $objectManager->create(
            \Magento\InventorySalesApi\Api\Data\SalesEventInterface::class,
            [
                'type'       => \Magento\InventorySalesApi\Api\Data\SalesEventInterface::EVENT_ORDER_PLACED,
                'objectType' => \Magento\InventorySalesApi\Api\Data\SalesEventInterface::OBJECT_TYPE_ORDER,
                'objectId'   => (string)$order->getEntityId(),
            ]
        );

        $objectManager->get(\Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface::class)
            ->execute($items, $salesChannel, $salesEvent);
    }

    /**
     * Single-source stock (no MSI): deduct the quantities again
     *
     * @param OrderInterface $order
     * @param array          $released
     *
     * @return void
     */
    private function registerLegacyStockSale(OrderInterface $order, array $released): void
    {
        $quantities = [];
        foreach ($released as [$productId, $qty]) {
            if ($productId > 0) {
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
            }
        }

        if ($quantities !== []) {
            ObjectManager::getInstance()->get(\Magento\CatalogInventory\Api\StockManagementInterface::class)
                ->registerProductsSale($quantities, (int)$order->getStore()->getWebsiteId());
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
