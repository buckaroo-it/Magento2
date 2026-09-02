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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\LogInterface;
use Buckaroo\Magento2\Api\Data\LogInterfaceFactory;
use Buckaroo\Magento2\Model\ResourceModel\Log\Collection;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

class Log extends AbstractModel
{
    /**
     * @var LogInterfaceFactory
     */
    protected $logDataFactory;

    /**
     * @var DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var string
     */
    protected $_eventPrefix = 'buckaroo_magento2_log';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param LogInterfaceFactory $logDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param ResourceModel\Log $resource
     * @param Collection $resourceCollection
     * @param array $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        Context $context,
        Registry $registry,
        LogInterfaceFactory $logDataFactory,
        DataObjectHelper $dataObjectHelper,
        ResourceModel\Log $resource,
        Collection $resourceCollection,
        array $data = []
    ) {
        $this->logDataFactory = $logDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve log model with log data
     *
     * @return LogInterface
     */
    public function getDataModel(): LogInterface
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
