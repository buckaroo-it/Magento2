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
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface SecondChanceRepositoryInterface
{
    /**
     * @param SecondChanceInterface $secondChance
     * @return SecondChanceInterface
     * @throws CouldNotSaveException
     */
    public function save(SecondChanceInterface $secondChance);

    /**
     * @param int|string $secondChanceId
     * @return SecondChanceInterface
     * @throws NoSuchEntityException
     */
    public function getById($secondChanceId);

    /**
     * @param SearchCriteria $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * @param SecondChanceInterface $secondChance
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SecondChanceInterface $secondChance);

    /**
     * @param $secondChanceId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($secondChanceId);
    
    /**
     * @param $order
     * @return bool
     */
    public function createSecondChance($order);
    
    /**
     * @param $token
     * @return bool
     */
    public function getSecondChanceByToken($token);

    /**
     * @param $step
     * @param $store
     * @return bool
     */
    public function getSecondChanceCollection($step, $store);
}
