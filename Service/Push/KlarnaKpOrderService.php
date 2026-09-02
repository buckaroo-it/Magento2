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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class KlarnaKpOrderService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder    $searchCriteriaBuilder
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Find an order by its Klarna KP reservation number stored in buckaroo_reservation_number.
     * Used as a fallback when a cancel push from Buckaroo plaza contains
     * brq_SERVICE_klarnakp_ReservationNumber but no standard transaction identifier.
     *
     * @param string $reservationNumber
     *
     * @return Order|null
     */
    public function getOrderByReservationNumber(string $reservationNumber): ?Order
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('buckaroo_reservation_number', $reservationNumber)
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        if (empty($orders)) {
            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [Service] | [%s:%s] - No order found by reservation number: %s',
                __METHOD__,
                __LINE__,
                $reservationNumber
            ));
            return null;
        }

        /** @var Order $order */
        return reset($orders);
    }
}
