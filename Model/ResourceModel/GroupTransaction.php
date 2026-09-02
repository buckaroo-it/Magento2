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

namespace Buckaroo\Magento2\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;

class GroupTransaction extends AbstractDb
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(
            'buckaroo_magento2_group_transaction',
            'entity_id'
        );
    }

    /**
     * Set the given status on every transaction that belongs to the group.
     *
     * @param string $relatedTransaction
     * @param string $status
     * @return int number of updated rows
     * @throws LocalizedException
     */
    public function updateStatusByRelatedTransaction(string $relatedTransaction, string $status): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $status],
            ['relatedtransaction = ?' => $relatedTransaction]
        );
    }
}
