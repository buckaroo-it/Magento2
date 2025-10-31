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
     * @param  SecondChanceInterface $secondChance
     * @throws LocalizedException
     * @return SecondChanceInterface
     */
    public function save(
        SecondChanceInterface $secondChance
    );

    /**
     * Retrieve SecondChance
     *
     * @param  string                                            $secondChanceId
     * @throws LocalizedException
     * @return SecondChanceInterface
     */
    public function get($secondChanceId);

    /**
     * Retrieve SecondChance by order ID
     *
     * @param  string                                            $orderId
     * @throws LocalizedException
     * @return SecondChanceInterface
     */
    public function getByOrderId(string $orderId);

    /**
     * Retrieve SecondChance matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SecondChanceSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete SecondChance
     *
     * @param  SecondChanceInterface $secondChance
     * @throws LocalizedException
     * @return bool                                              true on success
     */
    public function delete(
        SecondChanceInterface $secondChance
    );

    /**
     * Delete SecondChance by ID
     *
     * @param  string                                             $secondChanceId
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return bool                                               true on success
     */
    public function deleteById($secondChanceId);

    /**
     * Delete SecondChance by order ID
     *
     * @param  string                                             $orderId
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @return bool                                               true on success
     */
    public function deleteByOrderId($orderId);

    /**
     * Create second chance entry for order
     *
     * @param  OrderInterface            $order
     * @return SecondChanceInterface
     */
    public function createSecondChance($order);

    /**
     * Get second chance by token
     *
     * @param  string                                            $token
     * @return SecondChanceInterface
     */
    public function getSecondChanceByToken($token);

    /**
     * Delete older records based on configuration
     *
     * @param StoreInterface $store
     */
    public function deleteOlderRecords($store);

    /**
     * Get second chance collection for processing
     *
     * @param int                                    $step
     * @param StoreInterface $store
     */
    public function getSecondChanceCollection($step, $store);

    /**
     * Send second chance email
     *
     * @param OrderInterface            $order
     * @param SecondChanceInterface $secondChance
     * @param int                                               $step
     */
    public function sendMail($order, $secondChance, $step);
}
