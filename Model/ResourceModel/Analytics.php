<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ResourceModel;

class Analytics extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('buckaroo_magento2_analytics', 'analytics_id');
    }
}
