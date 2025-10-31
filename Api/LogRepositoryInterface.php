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
     * @param  LogInterface       $log
     * @throws LocalizedException
     * @return LogInterface
     */
    public function save(LogInterface $log);

    /**
     * Retrieve Log
     *
     * @param  string             $logId
     * @throws LocalizedException
     * @return LogInterface
     */
    public function get($logId);

    /**
     * Retrieve Log matching the specified criteria.
     *
     * @param  SearchCriteriaInterface   $searchCriteria
     * @throws LocalizedException
     * @return LogSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete Log
     *
     * @param  LogInterface       $log
     * @throws LocalizedException
     * @return bool               true on success
     */
    public function delete(LogInterface $log);

    /**
     * Delete Log by ID
     *
     * @param  string                $logId
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return bool                  true on success
     */
    public function deleteById($logId);
}
