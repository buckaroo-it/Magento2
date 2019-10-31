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
namespace TIG\Buckaroo\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use TIG\Buckaroo\Api\Data\GiftcardInterface;
use TIG\Buckaroo\Api\GiftcardRepositoryInterface;
use TIG\Buckaroo\Model\ResourceModel\Giftcard as GiftcardResource;
use TIG\Buckaroo\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use TIG\Buckaroo\Model\ResourceModel\Giftcard\CollectionFactory as GiftcardCollectionFactory;

class GiftcardRepository implements GiftcardRepositoryInterface
{
    /** @var GiftcardResource */
    protected $resource;

    /** @var GiftcardFactory */
    protected $giftcardFactory;

    /** @var GiftcardCollectionFactory */
    protected $giftcardCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    protected $searchResultsFactory;

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
     * {@inheritdoc}
     */
    public function save(GiftcardInterface $giftcard)
    {
        try {
            $this->resource->save($giftcard);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $giftcard;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getList(SearchCriteria $searchCriteria)
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
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param GiftcardCollection                        $collection
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
     * @param GiftcardCollection $collection
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
     * @param GiftcardCollection $collection
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
     * {@inheritdoc}
     */
    public function delete(GiftcardInterface $giftcard)
    {
        try {
            $this->resource->delete($giftcard);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($giftcardId)
    {
        $giftcard = $this->getById($giftcardId);

        return $this->delete($giftcard);
    }
}
