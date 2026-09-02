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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

interface TransactionResponseInterface
{
    /**
     * Get transaction id
     *
     * @return string
     */
    public function getTransactionId(): string;

    /**
     * Get giftcard name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Get amount
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Get giftcard code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set data
     *
     * @param array $data
     */
    public function addData(array $data);
}
