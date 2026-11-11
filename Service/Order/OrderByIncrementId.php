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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Repository-based replacement for the deprecated Order::loadByIncrementId().
 */
class OrderByIncrementId
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Fetch an order by its increment id, or null when it does not exist.
     *
     * Increment ids are only unique per store; pass the store when it is known.
     *
     * @param string   $incrementId
     * @param int|null $storeId
     * @return OrderInterface|null
     */
    public function get(string $incrementId, ?int $storeId = null): ?OrderInterface
    {
        $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId);
        if ($storeId !== null) {
            $this->searchCriteriaBuilder->addFilter('store_id', $storeId);
        }

        $orders = $this->orderRepository->getList(
            $this->searchCriteriaBuilder
                ->setPageSize(1)
                ->create()
        )->getItems();

        return array_shift($orders);
    }
}
