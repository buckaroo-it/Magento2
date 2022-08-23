<?php

namespace Buckaroo\Magento2\Gateway\Http\Client;

use Buckaroo\Magento2\Gateway\Http\Client\AbstractTransaction;

class TransactionPayOrPayEncrypted extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    protected function process(string $paymentMethod,  array $data)
    {
        if(isset($data['encryptedCardData'])) {
            return $this->adapter->payEncrypted($paymentMethod, $data);
        } else {
            return $this->adapter->pay($paymentMethod, $data);
        }

    }
}
