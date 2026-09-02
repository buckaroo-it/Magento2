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

interface TransactionStatusResponseInterface
{
    /**
     * Get status code
     *
     * @return string|null
     */
    public function getStatusCode();

    /**
     * Get service code
     *
     * @return string|null
     */
    public function getServiceCode();

    /**
     * Check if response is of status code
     *
     * @param mixed $statusCode
     *
     * @return bool
     */
    public function isStatusCode($statusCode);
}
