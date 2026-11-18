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

use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Puts back the received transaction status that a push recorded before it failed to apply
 *
 * The push stores "transaction key => status" before it updates the order, so a later delivery of the same status
 * can be recognised as a duplicate. When updating the order then fails, that entry must go again, otherwise
 * Buckaroo's retry is taken for a duplicate and the update is lost.
 *
 * Only this one entry is written, straight to the payment row: the in-memory payment may hold half-applied
 * changes from the failed push, and those must not be saved.
 */
class ReceivedTransactionStatusRestorer
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Json               $json
     */
    public function __construct(ResourceConnection $resourceConnection, Json $json)
    {
        $this->resourceConnection = $resourceConnection;
        $this->json = $json;
    }

    /**
     * Restore the entry a push recorded
     *
     * @param array $recorded as returned by DefaultProcessor::getRecordedTransactionStatus()
     *
     * @return void
     */
    public function restore(array $recorded): void
    {
        $paymentId = (int)($recorded['payment_id'] ?? 0);
        $transactionKey = (string)($recorded['transaction_key'] ?? '');
        if ($paymentId === 0 || $transactionKey === '') {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('sales_order_payment');

        $raw = $connection->fetchOne(
            $connection->select()->from($table, ['additional_information'])->where('entity_id = ?', $paymentId)
        );
        $information = $raw ? $this->json->unserialize($raw) : [];
        if (!is_array($information)) {
            return;
        }

        $statuses = $information[DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES] ?? [];
        if (!is_array($statuses)) {
            $statuses = [];
        }

        if (!empty($recorded['had_previous'])) {
            $statuses[$transactionKey] = $recorded['previous'];
        } else {
            unset($statuses[$transactionKey]);
        }
        $information[DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES] = $statuses;

        $connection->update(
            $table,
            ['additional_information' => $this->json->serialize($information)],
            ['entity_id = ?' => $paymentId]
        );
    }
}
