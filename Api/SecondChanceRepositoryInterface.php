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

use Magento\Framework\Api\SearchCriteriaInterface;

interface SecondChanceRepositoryInterface
{
    /**
     * Save SecondChance
     *
     * @param \Buckaroo\Magento2\Api\Data\SecondChanceInterface $secondChance
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Buckaroo\Magento2\Api\Data\SecondChanceInterface $secondChance
    );

    /**
     * Retrieve SecondChance
     *
     * @param string $secondChanceId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($secondChanceId);

    /**
     * Retrieve SecondChance by order ID
     *
     * @param string $orderId
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByOrderId(string $orderId);

    /**
     * Retrieve SecondChance matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete SecondChance
     *
     * @param \Buckaroo\Magento2\Api\Data\SecondChanceInterface $secondChance
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Buckaroo\Magento2\Api\Data\SecondChanceInterface $secondChance
    );

    /**
     * Delete SecondChance by ID
     *
     * @param string $secondChanceId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($secondChanceId);

    /**
     * Delete SecondChance by order ID
     *
     * @param string $orderId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteByOrderId($orderId);

    /**
     * Create second chance entry for order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function createSecondChance($order);

    /**
     * Get second chance by token
     *
     * @param string $token
     * @return \Buckaroo\Magento2\Api\Data\SecondChanceInterface
     */
    public function getSecondChanceByToken($token);

    /**
     * Delete older records based on configuration
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return void
     */
    public function deleteOlderRecords($store);

    /**
     * Get second chance collection for processing
     *
     * @param int $step
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return void
     */
    public function getSecondChanceCollection($step, $store);

    /**
     * Send second chance email
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Buckaroo\Magento2\Api\Data\SecondChanceInterface $secondChance
     * @param int $step
     * @return void
     */
    public function sendMail($order, $secondChance, $step);
}
