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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\TaxClass;

use Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation;
use Buckaroo\Magento2\Test\BaseTest;

class CalculationTest extends BaseTest
{
    protected $instanceClass = Calculation::class;

    public static function toOptionArrayProvider()
    {
        return [
            [['value' => 1, 'label' => 'Excluding Tax']],
            [['value' => 2, 'label' => 'Including Tax']],
        ];
    }

    /**
     * @param array $paymentOption
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result   = $instance->toOptionArray();

        // Normalize Magento results: cast labels to string, values to int
        $normalized = array_map(function ($opt) {
            return [
                'value' => (int)($opt['value'] ?? null),
                'label' => (string)($opt['label'] ?? ''),
            ];
        }, $result);

        // We only require that each expected option exists in the returned list
        $this->assertTrue(in_array($paymentOption, $normalized));
    }
}
