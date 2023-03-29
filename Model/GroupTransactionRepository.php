<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Api\Data\GroupTransactionInterface;
use Buckaroo\Magento2\Api\GroupTransactionRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction as GroupTransactionResource;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\Collection as GroupTransactionCollection;
use Buckaroo\Magento2\Model\ResourceModel\GroupTransaction\CollectionFactory as GroupTransactionCollectionFactory;

class GroupTransactionRepository implements GroupTransactionRepositoryInterface
{
    /** @var GroupTransactionResource */
    protected $resource;

    /** @var GroupTransactionFactory */
    protected $groupTransactionFactory;

    /** @var GroupTransactionCollectionFactory */
    protected $groupTransactionCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    protected $searchResultsFactory;

    public function __construct(
        GroupTransactionResource $resource,
        GroupTransactionFactory $groupTransactionFactory,
        GroupTransactionCollectionFactory $groupTransactionCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->GroupTransactionCollectionFactory = $groupTransactionCollectionFactory;
        $this->GroupTransactionFactory = $groupTransactionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(GroupTransactionInterface $groupTransaction)
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
    public function getById($groupTransactionId)
    {
        $groupTransaction = $this->GroupTransactionFactory->create();
        $groupTransaction->load($groupTransactionId);

        if (!$groupTransaction->getId()) {
            throw new NoSuchEntityException(__('GroupTransaction with id "%1" does not exist.', $groupTransactionId));
        }

        return $groupTransaction;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteria $searchCriteria)
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var GroupTransactionCollection $collection */
        $collection = $this->GroupTransactionCollectionFactory->create();

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
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param GroupTransactionCollection                        $collection
     */
    private function handleFilterGroups($filterGroup, $collection)
    {
        $fields     = [];
        $conditions = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition    = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[]     = $filter->getField();
            $conditions[] = [$condition => $filter->getValue()];
        }

        if ($fields) {
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @param GroupTransactionCollection $collection
     */
    private function handleSortOrders($searchCriteria, $collection)
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
     * @param SearchCriteria $searchCriteria
     * @param GroupTransactionCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems($searchCriteria, $collection)
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
    public function delete(GroupTransactionInterface $groupTransaction)
    {
        try {
            $this->resource->delete($groupTransaction);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById($groupTransactionId)
    {
        $groupTransaction = $this->getById($groupTransactionId);

        return $this->delete($groupTransaction);
    }
}
