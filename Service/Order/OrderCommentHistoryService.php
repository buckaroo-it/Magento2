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
