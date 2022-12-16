<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Config\Source;

class AfterpayCustomerType implements \Magento\Framework\Data\OptionSourceInterface
{
    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::CUSTOMER_TYPE_BOTH, 'label' => __('Both')],
            ['value' => self::CUSTOMER_TYPE_B2C, 'label' => __('B2C (Business-to-Consumer)')],
            ['value' => self::CUSTOMER_TYPE_B2B, 'label' => __('B2B (Business-to-Business)')]
        ];
    }
}
