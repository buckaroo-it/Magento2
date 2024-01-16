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
     * @return GroupTransactionInterface
     * @throws CouldNotSaveException
     */
    public function save(GroupTransactionInterface $groupTransaction);

    /**
     * Get group transaction by id
     *
     * @param int|string $groupTransactionId
     * @return GroupTransactionInterface
     * @throws NoSuchEntityException
     */
    public function getById($groupTransactionId);

    /**
     * Get the list of group transactions
     *
     * @param SearchCriteria $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * Delete group transaction
     *
     * @param GroupTransactionInterface $groupTransaction
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(GroupTransactionInterface $groupTransaction);

    /**
     * Delete group transaction by id
     *
     * @param int|string $groupTransactionId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($groupTransactionId);
}
