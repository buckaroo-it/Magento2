<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

class ExpressOrderIdDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $expressOrderId = $this->getPayment()->getAdditionalInformation('express_order_id');
        if ($expressOrderId !== null) {
            return ['payPalOrderId' => $expressOrderId];
        }

        return [];
    }
}
