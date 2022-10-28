<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Api\Data\OrderAddressInterface;

class OrderNumberDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        return ['order' => $this->getOrder()->getIncrementId()];
    }
}
