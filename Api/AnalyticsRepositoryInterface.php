<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\AnalyticsInterface;
use Buckaroo\Magento2\Api\Data\AnalyticsSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface AnalyticsRepositoryInterface
{

    /**
     * Save Analytics
     * @param  AnalyticsInterface  $analytics
     * @throws LocalizedException
     * @return AnalyticsInterface
     */
    public function save(
        AnalyticsInterface $analytics
    );

    /**
     * Retrieve Analytics
     * @param  string                                          $analyticsId
     * @throws LocalizedException
     * @return AnalyticsInterface
     */
    public function get($analyticsId);

    /**
     * Retrieve Analytics matching the specified criteria.
     * @param SearchCriteriaInterface $searchCriteria
     * @return AnalyticsSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete Analytics
     * @param  AnalyticsInterface  $analytics
     * @throws LocalizedException
     * @return bool                                            true on success
     */
    public function delete(
        AnalyticsInterface $analytics
    );

    /**
     * Delete Analytics by ID
     * @param  string                                             $analyticsId
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return bool                                               true on success
     */
    public function deleteById($analyticsId);
}
