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
use TIG\Buckaroo\Api\Data\CertificateInterface;
use TIG\Buckaroo\Api\CertificateRepositoryInterface;
use TIG\Buckaroo\Model\ResourceModel\Certificate as CertificateResource;
use TIG\Buckaroo\Model\ResourceModel\Certificate\Collection as CertificateCollection;
use TIG\Buckaroo\Model\ResourceModel\Certificate\CollectionFactory as CertificateCollectionFactory;

class CertificateRepository implements CertificateRepositoryInterface
{
    /** @var CertificateResource */
    protected $resource;

    /** @var CertificateFactory */
    protected $certificateFactory;

    /** @var CertificateCollectionFactory */
    protected $certificateCollectionFactory;

    /** @var SearchResultsInterfaceFactory */
    protected $searchResultsFactory;

    /**
     * @param CertificateResource           $resource
     * @param CertificateFactory            $certificateFactory
     * @param CertificateCollectionFactory  $certificateCollectionFactory
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
     * {@inheritdoc}
     */
    public function save(CertificateInterface $certificate)
    {
        try {
            $this->resource->save($certificate);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $certificate;
    }

    /**
     * {@inheritdoc}
     */
    public function getById($certificateId)
    {
        $certificate = $this->certificateFactory->create();
        $certificate->load($certificateId);

        if (!$certificate->getId()) {
            throw new NoSuchEntityException(__('Certificate with id "%1" does not exist.', $certificateId));
        }

        return $certificate;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteria $searchCriteria)
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
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param CertificateCollection                     $collection
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
     * @param CertificateCollection $collection
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
     * @param CertificateCollection $collection
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
    public function delete(CertificateInterface $certificate)
    {
        try {
            $this->resource->delete($certificate);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($certificateId)
    {
        $certificate = $this->getById($certificateId);

        return $this->delete($certificate);
    }
}
