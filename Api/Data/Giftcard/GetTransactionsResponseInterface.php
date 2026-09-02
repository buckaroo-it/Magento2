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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

interface GetTransactionsResponseInterface
{
    /**
     * Get the list of transactions for this cart
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface[]
     */
    public function getTransactions(): array;

    /**
     * Get RemainderAmount
     *
     * @api
     *
     * @return float
     */
    public function getRemainderAmount(): float;

    /**
     * Get AlreadyPaid
     *
     * @api
     *
     * @return float
     */
    public function getAlreadyPaid(): float;
}
