<?php

/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

interface LogSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get Log list.
     * @return \Buckaroo\Magento2\Api\Data\LogInterface[]
     */
    public function getItems();

    /**
     * Set log_id list.
     * @param \Buckaroo\Magento2\Api\Data\LogInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
