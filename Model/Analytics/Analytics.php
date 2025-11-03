<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Analytics;

use Buckaroo\Magento2\Api\Data\AnalyticsInterface;
use Buckaroo\Magento2\Api\Data\AnalyticsInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;

class Analytics extends \Magento\Framework\Model\AbstractModel
{

    protected $dataObjectHelper;

    protected $_eventPrefix = 'buckaroo_magento2_analytics';

    protected $analyticsDataFactory;

    /**
     * @param \Magento\Framework\Model\Context                            $context
     * @param \Magento\Framework\Registry                                 $registry
     * @param AnalyticsInterfaceFactory                                   $analyticsDataFactory
     * @param DataObjectHelper                                            $dataObjectHelper
     * @param \Buckaroo\Magento2\Model\ResourceModel\Analytics            $resource
     * @param \Buckaroo\Magento2\Model\ResourceModel\Analytics\Collection $resourceCollection
     * @param array                                                       $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        AnalyticsInterfaceFactory $analyticsDataFactory,
        DataObjectHelper $dataObjectHelper,
        \Buckaroo\Magento2\Model\ResourceModel\Analytics $resource,
        \Buckaroo\Magento2\Model\ResourceModel\Analytics\Collection $resourceCollection,
        array $data = []
    ) {
        $this->analyticsDataFactory = $analyticsDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve analytics model with analytics data
     *
     * @return AnalyticsInterface
     */
    public function getDataModel()
    {
        $analyticsData = $this->getData();

        $analyticsDataObject = $this->analyticsDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $analyticsDataObject,
            $analyticsData,
            AnalyticsInterface::class
        );

        return $analyticsDataObject;
    }
}
