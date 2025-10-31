<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface AnalyticsSearchResultsInterface extends SearchResultsInterface
{

    /**
     * Get Analytics list.
     * @return AnalyticsInterface[]
     */
    public function getItems();

    /**
     * Set quote_id list.
     * @param AnalyticsInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
