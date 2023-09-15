<?php

namespace Buckaroo\Magento2\Api\Data;

use Buckaroo\Transaction\Response\TransactionResponse;

interface BuckarooResponseDataInterface
{
    public function getResponse(): TransactionResponse;

    public function setResponse(TransactionResponse $transactionResponse): BuckarooResponseDataInterface;
}
