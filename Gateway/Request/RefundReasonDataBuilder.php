<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundReasonDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        return [
            'refundreason' => 'RequestedByCustomer',
        ];
    }
}
