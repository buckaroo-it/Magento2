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

namespace Buckaroo\Magento2\Api\Data\Ideal;

interface QuoteCreateResponseInterface
{
    /**
     * Get masked cart id
     *
     * @return string
     */
    public function getCartId();

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCurrencyCode(): string;

    /**
     * Get amount
     *
     * @return string
     */
    public function getValue(): string;
}
