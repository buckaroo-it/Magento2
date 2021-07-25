<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface AnalyticsRepositoryInterface
{

    /**
     * Save Analytics
     * @param \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
    );

    /**
     * Retrieve Analytics
     * @param string $analyticsId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($analyticsId);

    /**
     * Retrieve Analytics matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete Analytics
     * @param \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Buckaroo\Magento2\Api\Data\AnalyticsInterface $analytics
    );

    /**
     * Delete Analytics by ID
     * @param string $analyticsId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($analyticsId);
}
