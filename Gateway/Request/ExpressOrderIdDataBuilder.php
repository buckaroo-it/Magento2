<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ExpressOrderIdDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $expressOrderId = $paymentDO->getPayment()->getAdditionalInformation('express_order_id');
        if ($expressOrderId !== null) {
            return ['payPalOrderId' => $expressOrderId];
        }

        return [];
    }
}
