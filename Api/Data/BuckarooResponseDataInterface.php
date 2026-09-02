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

use Buckaroo\Transaction\Response\TransactionResponse;

interface BuckarooResponseDataInterface
{
    /**
     * Get the Buckaroo transaction response.
     *
     * @return TransactionResponse|null
     */
    public function getResponse(): ?TransactionResponse;

    /**
     * Set the Buckaroo transaction response.
     *
     * @param TransactionResponse $transactionResponse
     * @return BuckarooResponseDataInterface
     */
    public function setResponse(TransactionResponse $transactionResponse): BuckarooResponseDataInterface;
}
