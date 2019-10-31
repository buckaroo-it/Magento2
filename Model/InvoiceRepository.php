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
use TIG\Buckaroo\Api\Data\InvoiceInterface;
use TIG\Buckaroo\Api\InvoiceRepositoryInterface;
use TIG\Buckaroo\Model\ResourceModel\Invoice as InvoiceResource;
use TIG\Buckaroo\Model\ResourceModel\Invoice\Collection as InvoiceCollection;
use TIG\Buckaroo\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    /** @var InvoiceResource */
    protected $resource;

    /** @var InvoiceFactory */
    protected $invoiceFactory;

    /** @var InvoiceCollectionFactory */
    protected $invoiceCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    protected $searchResultsFactory;

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
     * {@inheritdoc}
     */
    public function save(InvoiceInterface $invoice)
    {
        try {
            $this->resource->save($invoice);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $invoice;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getList(SearchCriteria $searchCriteria)
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
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param InvoiceCollection                         $collection
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
     * @param InvoiceCollection $collection
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
     * @param InvoiceCollection $collection
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
    public function delete(InvoiceInterface $invoice)
    {
        try {
            $this->resource->delete($invoice);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($invoiceId)
    {
        $invoice = $this->getById($invoiceId);

        return $this->delete($invoice);
    }
}
