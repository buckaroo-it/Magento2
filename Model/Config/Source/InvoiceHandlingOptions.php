<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InvoiceHandlingOptions implements OptionSourceInterface
{
    public const INVOICE_HANDLING = 'invoice_handling';

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