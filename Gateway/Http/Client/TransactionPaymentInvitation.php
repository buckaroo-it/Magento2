<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;

class TransactionPaymentInvitation extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod,  array $data)
    {
        return $this->adapter->paymentInvitation($paymentMethod, $data);
    }
}
