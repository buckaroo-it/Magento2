<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class TinkaDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        return [
            'paymentMethod' => 'Credit',
            'deliveryMethod' => 'ShippingPartner'
        ];
    }
}
