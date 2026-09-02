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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\TaxClass;


use PHPUnit\Framework\Attributes\DataProvider;
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
     *
     */
    #[DataProvider('toOptionArrayProvider')]
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
