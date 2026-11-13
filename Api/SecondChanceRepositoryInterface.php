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

use Buckaroo\Magento2\Api\Data\SecondChanceInterface;
use Buckaroo\Magento2\Api\Data\SecondChanceSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\Data\StoreInterface;

interface SecondChanceRepositoryInterface
{
    /**
     * Save SecondChance
     *
     * @param SecondChanceInterface $secondChance
     *
     * @throws LocalizedException
     *
     * @return SecondChanceInterface
     */
    public function save(
        SecondChanceInterface $secondChance
    );

    /**
     * Retrieve SecondChance
     *
     * @param string $secondChanceId
     *
     * @throws LocalizedException
     *
     * @return SecondChanceInterface
     */
    public function get($secondChanceId);

    /**
     * Retrieve SecondChance by order ID
     *
     * @param string $orderId
     *
     * @throws LocalizedException
     *
     * @return SecondChanceInterface
     */
    public function getByOrderId(string $orderId);

    /**
     * Retrieve SecondChance matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @throws LocalizedException
     *
     * @return SecondChanceSearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete SecondChance
     *
     * @param SecondChanceInterface $secondChance
     *
     * @throws LocalizedException
     *
     * @return bool true on success
     */
    public function delete(
        SecondChanceInterface $secondChance
    );

    /**
     * Delete SecondChance by ID
     *
     * @param string $secondChanceId
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @return bool true on success
     */
    public function deleteById($secondChanceId);

    /**
     * Delete SecondChance by order ID
     *
     * @param string $orderId
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @return bool true on success
     */
    public function deleteByOrderId($orderId);

    /**
     * Create second chance entry for order
     *
     * @param OrderInterface $order
     *
     * @return SecondChanceInterface
     */
    public function createSecondChance($order);

    /**
     * Get second chance by token
     *
     * @param string $token
     *
     * @return SecondChanceInterface
     */
    public function getSecondChanceByToken($token);

    /**
     * Delete older records based on configuration
     *
     * @param StoreInterface $store
     *
     * @return int Number of records that were deleted
     */
    public function deleteOlderRecords($store);

    /**
     * Get second chance collection for processing
     *
     * @param int            $step
     * @param StoreInterface $store
     */
    public function getSecondChanceCollection($step, $store);

    /**
     * Send second chance email
     *
     * @param OrderInterface        $order
     * @param SecondChanceInterface $secondChance
     * @param int                   $step
     */
    public function sendMail($order, $secondChance, $step);
}
