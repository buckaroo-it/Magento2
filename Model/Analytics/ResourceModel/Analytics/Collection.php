<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Analytics\ResourceModel\Analytics;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'analytics_id';

    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(
            \Buckaroo\Magento2\Model\Analytics\Analytics::class,
            \Buckaroo\Magento2\Model\Analytics\ResourceModel\Analytics::class
        );
    }
}
