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
}
