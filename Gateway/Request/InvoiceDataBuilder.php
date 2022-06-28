<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class InvoiceDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject)
    {
        return ['invoice' => uniqid()];
    }
}
