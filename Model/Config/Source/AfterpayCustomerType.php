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

class AfterpayCustomerType implements OptionSourceInterface
{
    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CUSTOMER_TYPE_BOTH, 'label' => __('Both')],
            ['value' => self::CUSTOMER_TYPE_B2C, 'label' => __('B2C (Business-to-Consumer)')],
            ['value' => self::CUSTOMER_TYPE_B2B, 'label' => __('B2B (Business-to-Business)')]
        ];
    }
}
