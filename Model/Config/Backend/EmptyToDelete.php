<?php

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\App\ResourceConnection;

class EmptyToDelete extends Value
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Delete row from core_config_data if value is empty
     *
     * @return $this
     */
    public function beforeSave()
    {
        try {
            $value = $this->getValue();

            // If the value is empty, delete the row
            if (empty($value)) {
                $connection = $this->resourceConnection->getConnection();
                $tableName = $this->resourceConnection->getTableName('core_config_data');

                $connection->delete(
                    $tableName,
                    [
                        'path = ?' => $this->getPath(),
                        'scope = ?' => $this->getScope(),
                        'scope_id = ?' => $this->getScopeId()
                    ]
                );

                // Prevent saving an empty value
                $this->setValue(null);
            }
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
        }

        return parent::beforeSave();
    }
}
