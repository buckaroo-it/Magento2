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

use Buckaroo\Magento2\Api\Data\GiftcardInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface GiftcardRepositoryInterface
{
    /**
     * Save gift card
     *
     * @param GiftcardInterface $giftcard
     * @return GiftcardInterface
     * @throws CouldNotSaveException
     */
    public function save(GiftcardInterface $giftcard): GiftcardInterface;

    /**
     * Get gift card by id
     *
     * @param int|string $giftcardId
     * @return GiftcardInterface
     * @throws NoSuchEntityException
     */
    public function getById($giftcardId);

    /**
     * Get the list of gift cards
     *
     * @param SearchCriteria $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * Delete gift card
     *
     * @param GiftcardInterface $giftcard
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(GiftcardInterface $giftcard);

    /**
     * Delete gift card by certificate id
     *
     * @param int|string $giftcardId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($giftcardId);
}
