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

namespace Buckaroo\Magento2\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SecondChance extends AbstractDb
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('buckaroo_magento2_second_chance', 'entity_id');
    }

    /**
     * Atomically move a record from one status to another
     *
     * Returns false when another request changed the status first, so a magic link
     * can only be redeemed once even when it is opened twice at the same time.
     *
     * @param int    $entityId
     * @param string $fromStatus
     * @param string $toStatus
     * @return bool
     */
    public function claimStatus(int $entityId, string $fromStatus, string $toStatus): bool
    {
        $affected = $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $toStatus],
            [
                'entity_id = ?' => $entityId,
                'status = ?' => $fromStatus,
            ]
        );

        return $affected === 1;
    }

    /**
     * Read the status a record has in the database right now
     *
     * @param int $entityId
     * @return string|null Null when the record no longer exists
     */
    public function getCurrentStatus(int $entityId): ?string
    {
        $connection = $this->getConnection();
        // fetchOne() gives false when there is no row; the status column is never empty
        $status = (string) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['status'])
                ->where('entity_id = ?', $entityId)
        );

        return $status === '' ? null : $status;
    }

    /**
     * Save an existing record only if it still has the status it was loaded with
     *
     * Background jobs work on records loaded earlier. A plain save would write their stale copy
     * over a change made meanwhile: a reminder link redeemed in between, or a record reactivated
     * with a new token for a follow-up order. When the status changed, nothing is written.
     *
     * @param AbstractModel $record
     * @param string        $expectedStatus Status the record had when it was loaded
     * @return void
     */
    public function saveIfStatusUnchanged(AbstractModel $record, string $expectedStatus): void
    {
        $data = $this->_prepareDataForSave($record);
        unset($data[$this->getIdFieldName()]);

        $this->getConnection()->update(
            $this->getMainTable(),
            $data,
            [
                'entity_id = ?' => (int) $record->getId(),
                'status = ?' => $expectedStatus,
            ]
        );
    }
}
