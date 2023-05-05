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

use Buckaroo\Magento2\Api\Data\InvoiceInterface;
use Buckaroo\Magento2\Api\InvoiceRepositoryInterface;
use Buckaroo\Magento2\Model\ResourceModel\Invoice as InvoiceResource;
use Buckaroo\Magento2\Model\ResourceModel\Invoice\Collection as InvoiceCollection;
use Buckaroo\Magento2\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
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
class InvoiceRepository implements InvoiceRepositoryInterface
{
    /**
     * @var InvoiceResource
     */
    protected InvoiceResource $resource;

    /**
     * @var InvoiceFactory
     */
    protected InvoiceFactory $invoiceFactory;

    /**
     * @var InvoiceCollectionFactory
     */
    protected InvoiceCollectionFactory $invoiceCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected SearchResultsInterfaceFactory $searchResultsFactory;

    public function __construct(
        InvoiceResource $resource,
        InvoiceFactory $invoiceFactory,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceFactory = $invoiceFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(InvoiceInterface $invoice): InvoiceInterface
    {
        try {
            $this->resource->save($invoice);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $invoice;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteria $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var InvoiceCollection $collection */
        $collection = $this->invoiceCollectionFactory->create();

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
     * @param FilterGroup $filterGroup
     * @param InvoiceCollection $collection
     */
    private function handleFilterGroups(FilterGroup $filterGroup, InvoiceCollection $collection)
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
     * @param SearchCriteria $searchCriteria
     * @param InvoiceCollection $collection
     */
    private function handleSortOrders(SearchCriteria $searchCriteria, InvoiceCollection $collection)
    {
        $sortOrders = $searchCriteria->getSortOrders();

        if (!$sortOrders) {
            return;
        }

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
     * @param SearchCriteria $searchCriteria
     * @param InvoiceCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems(SearchCriteria $searchCriteria, InvoiceCollection $collection): array
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
    public function deleteById($invoiceId): bool
    {
        $invoice = $this->getById($invoiceId);

        return $this->delete($invoice);
    }

    /**
     * @inheritdoc
     */
    public function getById($invoiceId)
    {
        $invoice = $this->invoiceFactory->create();
        $invoice->load($invoiceId);

        if (!$invoice->getId()) {
            throw new NoSuchEntityException(__('Invoice with id "%1" does not exist.', $invoiceId));
        }

        return $invoice;
    }

    /**
     * @inheritdoc
     */
    public function delete(InvoiceInterface $invoice): bool
    {
        try {
            $this->resource->delete($invoice);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }
}
