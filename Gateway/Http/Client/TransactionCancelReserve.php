<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;
use Buckaroo\Transaction\Response\TransactionResponse;

class TransactionCancelReserve extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod,  array $data): TransactionResponse
    {
        return $this->adapter->cancelReserve($paymentMethod, $data);
    }
}