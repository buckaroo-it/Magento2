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
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface LogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get Log list.
     *
     * @return LogInterface[]
     */
    public function getItems(): array;

    /**
     * Set log_id list.
     *
     * @param LogInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items): LogSearchResultsInterface;
}
