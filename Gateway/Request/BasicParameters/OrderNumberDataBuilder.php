<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BasicParameters;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class OrderNumberDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        return ['order' => $paymentDO->getOrder()->getOrder()->getIncrementId()];
    }
}
