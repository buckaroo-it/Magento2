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

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;

/**
 * Persists order status-history comments through the sales repository API,
 * replacing the deprecated AbstractModel::save() chains on history records.
 */
class OrderCommentHistoryService
{
    /**
     * @var HistoryFactory
     */
    private $historyFactory;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private $historyRepository;

    /**
     * @param HistoryFactory                        $historyFactory
     * @param OrderStatusHistoryRepositoryInterface $historyRepository
     */
    public function __construct(
        HistoryFactory $historyFactory,
        OrderStatusHistoryRepositoryInterface $historyRepository
    ) {
        $this->historyFactory = $historyFactory;
        $this->historyRepository = $historyRepository;
    }

    /**
     * Add a comment to the order status history and persist it via the repository.
     *
     * The order itself is NOT saved; only the history record is written.
     *
     * @param OrderInterface                   $order
     * @param \Magento\Framework\Phrase|string $message
     * @param bool                             $isCustomerNotified
     * @param string|null                      $status Status recorded on the history row;
     *                                                 defaults to the order's current status
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function add($order, $message, bool $isCustomerNotified = false, ?string $status = null): void
    {
        $messageText = (string)$message;
        if ($messageText === '') {
            return;
        }

        /** @var OrderStatusHistoryInterface $history */
        $history = $this->historyFactory->create();
        $history->setParentId($order->getEntityId())
            ->setComment($messageText)
            ->setStatus($status ?? $order->getStatus())
            ->setIsCustomerNotified($isCustomerNotified)
            ->setEntityName('order');

        $this->historyRepository->save($history);
    }
}
