<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InvoiceHandlingOptions implements OptionSourceInterface
{
    public const INVOICE_HANDLING = 'invoice_handling';

    public const PAYMENT = 1;
    public const SHIPMENT = 2;

    /**
     * Return the invoice handling options for the system configuration dropdown.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PAYMENT, 'label' => __('Create Invoice on Payment')],
            ['value' => self::SHIPMENT, 'label' => __('Create Invoice on Shipment')]
        ];
    }
}
