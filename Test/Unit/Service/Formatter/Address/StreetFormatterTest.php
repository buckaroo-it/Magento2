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

namespace Buckaroo\Magento2\Test\Unit\Service\Formatter\Address;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter;
use Buckaroo\Magento2\Test\BaseTest;

class StreetFormatterTest extends BaseTest
{
    protected $instanceClass = StreetFormatter::class;

    /**
     * @return array
     */
    public static function formatProvider()
    {
        return [
            'street only' => [
                ['Kabelweg'],
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '',
                    'number_addition' => '',
                ]
            ],
            'with housenumber' => [
                ['Kabelweg 37'],
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => '',
                ]
            ],
            'with number addition' => [
                ['Kabelweg', '37 1'],
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => '1',
                ]
            ],
            'with letter addition' => [
                ['Kabelweg 37', 'A'],
                [
                    'street'          => 'Kabelweg',
                    'house_number'    => '37',
                    'number_addition' => 'A',
                ]
            ],
        ];
    }

    /**
     * @param $street
     * @param $expected
     *
     */
    #[DataProvider('formatProvider')]
    public function testFormat($street, $expected)
    {
        $instance = $this->getInstance();
        $result = $instance->format($street);

        $this->assertEquals($expected, $result);
    }
}
