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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\Display;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\Config\Source\Display\Type;
use Buckaroo\Magento2\Test\BaseTest;

class TypeTest extends BaseTest
{
    protected $instanceClass = Type::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 1, 'label' => 'Excluding Tax']
            ],
            [
                ['value' => 2, 'label' => 'Including Tax']
            ],
            [
                ['value' => 3, 'label' => 'Including and Excluding Tax']
            ]
        ];
    }

    /**
     * @param $paymentOption
     *
     */
    #[DataProvider('toOptionArrayProvider')]
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $found = false;
        foreach ($result as $option) {
            if ($option['value'] === $paymentOption['value']) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
