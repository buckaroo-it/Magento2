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

use Magento\Framework\Phrase;

/**
 * Interface PayResponseInterface
 *
 * @api
 */
interface PayResponseInterface
{
    /**
     * Get RemainderAmount
     *
     * @return float
     *
     * @api
     */
    public function getRemainderAmount(): float;

    /**
     * Get AlreadyPaid
     *
     * @return float
     *
     * @api
     */
    public function getAlreadyPaid(): float;

    /**
     * Get newly created transaction with giftcard name
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface
     */
    public function getTransaction(): TransactionResponseInterface;

    /**
     * Get user message
     *
     * @return string|null
     *
     * @api
     */
    public function getMessage();

    /**
     * Get user remaining amount message
     *
     * @return string|null
     *
     * @api
     */
    public function getRemainingAmountMessage();
}
