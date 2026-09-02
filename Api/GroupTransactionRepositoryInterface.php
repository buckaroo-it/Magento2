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

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\GroupTransactionInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface GroupTransactionRepositoryInterface
{
    /**
     * Save group transaction
     *
     * @param GroupTransactionInterface $groupTransaction
     *
     * @throws CouldNotSaveException
     *
     * @return GroupTransactionInterface
     */
    public function save(GroupTransactionInterface $groupTransaction);

    /**
     * Get group transaction by id
     *
     * @param int|string $groupTransactionId
     *
     * @throws NoSuchEntityException
     *
     * @return GroupTransactionInterface
     */
    public function getById($groupTransactionId);

    /**
     * Get the list of group transactions
     *
     * @param SearchCriteria $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * Delete group transaction
     *
     * @param GroupTransactionInterface $groupTransaction
     *
     * @throws CouldNotDeleteException
     *
     * @return bool
     */
    public function delete(GroupTransactionInterface $groupTransaction);

    /**
     * Delete group transaction by id
     *
     * @param int|string $groupTransactionId
     *
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function deleteById($groupTransactionId);
}
