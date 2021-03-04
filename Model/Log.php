<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\LogInterface;
use Buckaroo\Magento2\Api\Data\LogInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;

class Log extends \Magento\Framework\Model\AbstractModel
{

    protected $logDataFactory;

    protected $dataObjectHelper;

    protected $_eventPrefix = 'buckaroo_magento2_log';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param LogInterfaceFactory $logDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param \Buckaroo\Magento2\Model\ResourceModel\Log $resource
     * @param \Buckaroo\Magento2\Model\ResourceModel\Log\Collection $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        LogInterfaceFactory $logDataFactory,
        DataObjectHelper $dataObjectHelper,
        \Buckaroo\Magento2\Model\ResourceModel\Log $resource,
        \Buckaroo\Magento2\Model\ResourceModel\Log\Collection $resourceCollection,
        array $data = []
    ) {
        $this->logDataFactory = $logDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve log model with log data
     * @return LogInterface
     */
    public function getDataModel()
    {
        $logData = $this->getData();
        
        $logDataObject = $this->logDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $logDataObject,
            $logData,
            LogInterface::class
        );
        
        return $logDataObject;
    }
}
