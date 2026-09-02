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
     *
     * @throws CouldNotSaveException
     *
     * @return GiftcardInterface
     */
    public function save(GiftcardInterface $giftcard): GiftcardInterface;

    /**
     * Get gift card by id
     *
     * @param int|string $giftcardId
     *
     * @throws NoSuchEntityException
     *
     * @return GiftcardInterface
     */
    public function getById($giftcardId);

    /**
     * Get the list of gift cards
     *
     * @param SearchCriteria $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * Delete gift card
     *
     * @param GiftcardInterface $giftcard
     *
     * @throws CouldNotDeleteException
     *
     * @return bool
     */
    public function delete(GiftcardInterface $giftcard);

    /**
     * Delete gift card by certificate id
     *
     * @param int|string $giftcardId
     *
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function deleteById($giftcardId);

    /**
     * Get giftcard by service code
     *
     * @param string $serviceCode
     *
     * @return GiftcardInterface
     */
    public function getByServiceCode(string $serviceCode);
}
