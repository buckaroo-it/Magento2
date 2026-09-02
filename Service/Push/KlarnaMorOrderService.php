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

namespace Buckaroo\Magento2\Service\Push;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class KlarnaMorOrderService
{
    public const PENDING_DATAREQUEST_PUSH_KEYS = 'buckaroo_mor_pending_datarequest_keys';

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder    $searchCriteriaBuilder
     * @param ResourceConnection       $resourceConnection
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Find an order by its stored Klarna MOR DataRequest key.
     *
     * @param string $dataRequestKey
     *
     * @return Order|null
     */
    public function getOrderByDataRequestKey(string $dataRequestKey): ?Order
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('buckaroo_datarequest_key', $dataRequestKey)
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        if (empty($orders)) {
            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [Service] | [%s:%s] - No order found by DataRequest key: %s',
                __METHOD__,
                __LINE__,
                $dataRequestKey
            ));
            return null;
        }

        /** @var Order $order */
        return reset($orders);
    }

    /**
     * Find an order by a pending Extend/Update reservation push key stored on payment.
     *
     * @param string $pushKey
     *
     * @return Order|null
     */
    public function getOrderByPendingDataRequestPushKey(string $pushKey): ?Order
    {
        $connection = $this->resourceConnection->getConnection();
        $paymentTable = $this->resourceConnection->getTableName('sales_order_payment');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(['sop' => $paymentTable], [])
            ->join(
                ['so' => $orderTable],
                'sop.parent_id = so.entity_id',
                ['entity_id']
            )
            ->where('sop.additional_information LIKE ?', '%' . $pushKey . '%')
            ->limit(1);

        $orderId = $connection->fetchOne($select);

        if (!$orderId) {
            $this->logger->addDebug(sprintf(
                '[KLARNA_MOR] | [Service] | [%s:%s] - No order found by pending DataRequest push key: %s',
                __METHOD__,
                __LINE__,
                $pushKey
            ));
            return null;
        }

        return $this->orderRepository->get((int)$orderId);
    }
}
