<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;

class TransactionReserve extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod,  array $data)
    {
        return $this->adapter->reserve($paymentMethod, $data);
    }
}
