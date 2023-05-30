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

use Buckaroo\Magento2\Api\CertificateRepositoryInterface;
use Buckaroo\Magento2\Api\Data\CertificateInterface;
use Buckaroo\Magento2\Model\ResourceModel\Certificate as CertificateResource;
use Buckaroo\Magento2\Model\ResourceModel\Certificate\Collection as CertificateCollection;
use Buckaroo\Magento2\Model\ResourceModel\Certificate\CollectionFactory as CertificateCollectionFactory;
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
class CertificateRepository implements CertificateRepositoryInterface
{
    /**
     * @var CertificateResource
     */
    protected CertificateResource $resource;

    /**
     * @var CertificateFactory
     */
    protected CertificateFactory $certificateFactory;

    /**
     * @var CertificateCollectionFactory
     */
    protected CertificateCollectionFactory $certificateCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @param CertificateResource $resource
     * @param CertificateFactory $certificateFactory
     * @param CertificateCollectionFactory $certificateCollectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        CertificateResource $resource,
        CertificateFactory $certificateFactory,
        CertificateCollectionFactory $certificateCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->certificateCollectionFactory = $certificateCollectionFactory;
        $this->certificateFactory = $certificateFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(CertificateInterface $certificate): CertificateInterface
    {
        try {
            $this->resource->save($certificate);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $certificate;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteria $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        /** @var CertificateCollection $collection */
        $collection = $this->certificateCollectionFactory->create();

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
     * @param CertificateCollection $collection
     * @return void
     */
    private function handleFilterGroups(FilterGroup $filterGroup, CertificateCollection $collection)
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
     * @param CertificateCollection $collection
     */
    private function handleSortOrders(SearchCriteria $searchCriteria, CertificateCollection $collection)
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
     * @param CertificateCollection $collection
     *
     * @return array
     */
    private function getSearchResultItems(SearchCriteria $searchCriteria, CertificateCollection $collection): array
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
    public function deleteById($certificateId): bool
    {
        $certificate = $this->getById($certificateId);

        return $this->delete($certificate);
    }

    /**
     * @inheritdoc
     */
    public function getById($certificateId): CertificateInterface
    {
        $certificate = $this->certificateFactory->create();
        $certificate->load($certificateId);

        if (!$certificate->getId()) {
            throw new NoSuchEntityException(__('Certificate with id "%1" does not exist.', $certificateId));
        }

        return $certificate;
    }

    /**
     * @inheritdoc
     */
    public function delete(CertificateInterface $certificate): bool
    {
        try {
            $this->resource->delete($certificate);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }
}
