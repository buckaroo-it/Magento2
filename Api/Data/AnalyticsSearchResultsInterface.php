<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

interface AnalyticsSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get Analytics list.
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface[]
     */
    public function getItems();

    /**
     * Set quote_id list.
     * @param \Buckaroo\Magento2\Api\Data\AnalyticsInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
