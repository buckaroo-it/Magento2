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
namespace TIG\Buckaroo\Test\Unit\Service\Formatter\Address;

use TIG\Buckaroo\Service\Formatter\Address\StreetFormatter;
use TIG\Buckaroo\Test\BaseTest;

class StreetFormatterTest extends BaseTest
{
    protected $instanceClass = StreetFormatter::class;

    /**
     * @return array
     */
    public function formatProvider()
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
     * @dataProvider formatProvider
     */
    public function testFormat($street, $expected)
    {
        $instance = $this->getInstance();
        $result = $instance->format($street);

        $this->assertEquals($expected, $result);
    }
}
