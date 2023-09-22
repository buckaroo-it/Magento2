<?php

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InvoiceHandlingOptions implements OptionSourceInterface
{
    public const PAYMENT = 1;
    public const SHIPMENT = 2;

    public function toOptionArray(): array
    {
        return [
            ['value' => self::PAYMENT, 'label' => __('Create Invoice on Payment')],
            ['value' => self::SHIPMENT, 'label' => __('Create Invoice on Shipment')]
        ];
    }
}