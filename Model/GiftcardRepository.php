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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\Data\GiftcardInterface;
use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard as GiftcardResource;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\CollectionFactory as GiftcardCollectionFactory;
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
class GiftcardRepository implements GiftcardRepositoryInterface
{
    /**
     * @var GiftcardResource
     */
    protected $resource;

    /**
     * @var GiftcardFactory
     */
    protected $giftcardFactory;

    /**
     * @var GiftcardCollectionFactory
     */
    protected $giftcardCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @param GiftcardResource              $resource
     * @param GiftcardFactory               $giftcardFactory
     * @param GiftcardCollectionFactory     $giftcardCollectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        GiftcardResource $resource,
        GiftcardFactory $giftcardFactory,
        GiftcardCollectionFactory $giftcardCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->giftcardCollectionFactory = $giftcardCollectionFactory;
        $this->giftcardFactory = $giftcardFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(GiftcardInterface $giftcard): GiftcardInterface
    {
        try {
            $this->resource->save($giftcard);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $giftcard;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteria $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var GiftcardCollection $collection */
        $collection = $this->giftcardCollectionFactory->create();

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
     * @param FilterGroup        $filterGroup
     * @param GiftcardCollection $collection
     */
    private function handleFilterGroups(FilterGroup $filterGroup, GiftcardCollection $collection)
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
     * @param SearchCriteria     $searchCriteria
     * @param GiftcardCollection $collection
     */
    private function handleSortOrders(SearchCriteria $searchCriteria, GiftcardCollection $collection)
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
     * @param SearchCriteria     $searchCriteria
     * @param GiftcardCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems(SearchCriteria $searchCriteria, GiftcardCollection $collection): array
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
    public function deleteById($giftcardId): bool
    {
        $giftcard = $this->getById($giftcardId);

        return $this->delete($giftcard);
    }

    /**
     * @inheritdoc
     */
    public function getById($giftcardId)
    {
        $giftcard = $this->giftcardFactory->create();
        $giftcard->load($giftcardId);

        if (!$giftcard->getId()) {
            throw new NoSuchEntityException(__('Giftcard with id "%1" does not exist.', $giftcardId));
        }

        return $giftcard;
    }

    /**
     * @inheritdoc
     */
    public function delete(GiftcardInterface $giftcard): bool
    {
        try {
            $this->resource->delete($giftcard);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @param  string            $serviceCode
     * @return GiftcardInterface
     */
    public function getByServiceCode(string $serviceCode)
    {
        /** @var GiftcardCollection $collection */
        $collection = $this->giftcardCollectionFactory->create();

        return $collection->addFieldToFilter(
            'servicecode',
            ['eq' => $serviceCode]
        )
            ->getLastItem();
    }
}
