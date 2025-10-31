<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\GroupTransactionInterface;
use Buckaroo\Magento2\Api\GroupTransactionRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\Collection as GroupTransactionCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory as GroupTransactionCollectionFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GroupTransactionRepository implements GroupTransactionRepositoryInterface
{
    /**
     * @var GroupTransactionResource
     */
    protected $resource;

    /**
     * @var GroupTransactionFactory
     */
    protected $groupTransactionFactory;

    /**
     * @var GroupTransactionCollectionFactory
     */
    protected $groupTransactionCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @param GroupTransactionResource          $resource
     * @param GroupTransactionFactory           $groupTransactionFactory
     * @param GroupTransactionCollectionFactory $groupTransactionCollectionFactory
     * @param SearchResultsInterfaceFactory     $searchResultsFactory
     */
    public function __construct(
        GroupTransactionResource $resource,
        GroupTransactionFactory $groupTransactionFactory,
        GroupTransactionCollectionFactory $groupTransactionCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->groupTransactionCollectionFactory = $groupTransactionCollectionFactory;
        $this->groupTransactionFactory = $groupTransactionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(GroupTransactionInterface $groupTransaction): GroupTransactionInterface
    {
        try {
            $this->resource->save($groupTransaction);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $groupTransaction;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteria $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var GroupTransactionCollection $collection */
        $collection = $this->groupTransactionCollectionFactory->create();

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $this->handleFilterGroups($filterGroup, $collection);
        }

        $searchResults->setTotalCount($collection->getSize());
        $this->handleSortOrders($searchCriteria, $collection);

        $items = $this->getSearchResultItems($searchCriteria, $collection);
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * Handle filter groups for the given collection by applying filters from the filter group.
     *
     * @param FilterGroup                $filterGroup
     * @param GroupTransactionCollection $collection
     */
    private function handleFilterGroups(FilterGroup $filterGroup, GroupTransactionCollection $collection)
    {
        $fields = [];
        $conditions = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = $filter->getField();
            $conditions[] = [$condition => $filter->getValue()];
        }

        if ($fields) {
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * Handle sort orders for the given search criteria and collection.
     *
     * @param SearchCriteria             $searchCriteria
     * @param GroupTransactionCollection $collection
     */
    private function handleSortOrders(SearchCriteria $searchCriteria, GroupTransactionCollection $collection)
    {
        $sortOrders = $searchCriteria->getSortOrders();

        if (!$sortOrders) {
            return;
        }

        /** @var SortOrder $sortOrder */
        foreach ($sortOrders as $sortOrder) {
            $collection->addOrder(
                $sortOrder->getField(),
                ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
    }

    /**
     * Get search result items based on search criteria and collection.
     *
     * @param SearchCriteria             $searchCriteria
     * @param GroupTransactionCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems(SearchCriteria $searchCriteria, GroupTransactionCollection $collection): array
    {
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $items = [];

        foreach ($collection as $testieModel) {
            $items[] = $testieModel;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function deleteById($groupTransactionId): bool
    {
        $groupTransaction = $this->getById($groupTransactionId);

        return $this->delete($groupTransaction);
    }

    /**
     * @inheritdoc
     */
    public function getById($groupTransactionId)
    {
        $groupTransaction = $this->groupTransactionFactory->create();
        $groupTransaction->load($groupTransactionId);

        if (!$groupTransaction->getId()) {
            throw new NoSuchEntityException(__('GroupTransaction with id "%1" does not exist.', $groupTransactionId));
        }

        return $groupTransaction;
    }

    /**
     * @inheritdoc
     */
    public function delete(GroupTransactionInterface $groupTransaction): bool
    {
        try {
            $this->resource->delete($groupTransaction);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * Get transaction by id and order
     *
     * @param  string                $transactionId
     * @param  string                $orderId
     * @return GroupTransaction|null
     */
    public function getTransactionByIdAndOrderId(string $transactionId, string $orderId): ?GroupTransaction
    {
        /** @var GroupTransactionCollection $collection */
        $collection = $this->groupTransactionCollectionFactory->create();

        return $collection->addFieldToFilter(
            'transaction_id',
            ['eq' => $transactionId]
        )
        ->addFieldToFilter(
            'order_id',
            ['eq' => $orderId]
        )
        ->getLastItem();
    }
}
