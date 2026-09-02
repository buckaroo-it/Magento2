<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\LogInterface;
use Buckaroo\Magento2\Api\Data\LogSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface LogRepositoryInterface
{
    /**
     * Save Log
     *
     * @param LogInterface $log
     *
     * @throws LocalizedException
     *
     * @return LogInterface
     */
    public function save(LogInterface $log);

    /**
     * Retrieve Log
     *
     * @param string $logId
     *
     * @throws LocalizedException
     *
     * @return LogInterface
     */
    public function get($logId);

    /**
     * Retrieve Log matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @throws LocalizedException
     *
     * @return LogSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete Log
     *
     * @param LogInterface $log
     *
     * @throws LocalizedException
     *
     * @return bool true on success
     */
    public function delete(LogInterface $log);

    /**
     * Delete Log by ID
     *
     * @param string $logId
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @return bool true on success
     */
    public function deleteById($logId);
}
