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

namespace Buckaroo\Magento2\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface SecondChanceSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get SecondChance list.
     *
     * @return SecondChanceInterface[]
     */
    public function getItems();

    /**
     * Set SecondChance list.
     *
     * @param SecondChanceInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
