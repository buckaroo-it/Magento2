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
}
