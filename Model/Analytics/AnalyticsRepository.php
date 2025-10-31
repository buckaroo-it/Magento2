<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Analytics;

use Buckaroo\Magento2\Api\AnalyticsRepositoryInterface;
use Buckaroo\Magento2\Api\Data\AnalyticsInterfaceFactory;
use Buckaroo\Magento2\Api\Data\AnalyticsSearchResultsInterfaceFactory;
use Buckaroo\Magento2\Model\ResourceModel\Analytics as ResourceAnalytics;
use Buckaroo\Magento2\Model\ResourceModel\Analytics\CollectionFactory as AnalyticsCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;
/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AnalyticsRepository implements AnalyticsRepositoryInterface
{

    protected $analyticsFactory;

    protected $resource;

    protected $searchResultsFactory;

    protected $extensibleDataObjectConverter;
    protected $analyticsCollectionFactory;

    private $storeManager;

    protected $dataAnalyticsFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $extensionAttributesJoinProcessor;

    private $collectionProcessor;

    /**
     * @param ResourceAnalytics                      $resource
     * @param AnalyticsFactory                       $analyticsFactory
     * @param AnalyticsInterfaceFactory              $dataAnalyticsFactory
     * @param AnalyticsCollectionFactory             $analyticsCollectionFactory
     * @param AnalyticsSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper                       $dataObjectHelper
     * @param DataObjectProcessor                    $dataObjectProcessor
     * @param StoreManagerInterface                  $storeManager
     * @param CollectionProcessorInterface           $collectionProcessor
     * @param JoinProcessorInterface                 $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter          $extensibleDataObjectConverter
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResourceAnalytics $resource,
        AnalyticsFactory $analyticsFactory,
        AnalyticsInterfaceFactory $dataAnalyticsFactory,
        AnalyticsCollectionFactory $analyticsCollectionFactory,
        AnalyticsSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->analyticsFactory = $analyticsFactory;
        $this->analyticsCollectionFactory = $analyticsCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataAnalyticsFactory = $dataAnalyticsFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
    ) {
        /* if (empty($analytics->getStoreId())) {
            $storeId = $this->storeManager->getStore()->getId();
            $analytics->setStoreId($storeId);
        } */

        $analyticsData = $this->extensibleDataObjectConverter->toNestedArray(
            $analytics,
            [],
            \Buckaroo\Magento2\Api\Data\AnalyticsInterface::class
        );

        $analyticsModel = $this->analyticsFactory->create()->setData($analyticsData);

        try {
            $this->resource->save($analyticsModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the analytics: %1',
                $exception->getMessage()
            ));
        }
        return $analyticsModel->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function get($analyticsId)
    {
        $analytics = $this->analyticsFactory->create();
        $this->resource->load($analytics, $analyticsId);
        if (!$analytics->getId()) {
            throw new NoSuchEntityException(__('Analytics data with id "%1" does not exist.', $analyticsId));
        }
        return $analytics->getDataModel();
    }

    public function getByQuoteId($quoteId)
    {
        $analytics = $this->analyticsFactory->create();
        $this->resource->load($analytics, $quoteId, 'quote_id');
        if (!$analytics->getId()) {
            throw new NoSuchEntityException(__('Analytics data with quote_id "%1" does not exist.', $quoteId));
        }
        return $analytics->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->analyticsCollectionFactory->create();

        $this->extensionAttributesJoinProcessor->process(
            $collection,
            \Buckaroo\Magento2\Api\Data\AnalyticsInterface::class
        );

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model->getDataModel();
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
    ) {
        try {
            $analyticsModel = $this->analyticsFactory->create();
            $this->resource->load($analyticsModel, $analytics->getAnalyticsId());
            $this->resource->delete($analyticsModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the Analytics: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($analyticsId)
    {
        return $this->delete($this->get($analyticsId));
    }
}
