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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;

use Buckaroo\Magento2\Model\Config\Source\AllOrSpecificCountries;
use Buckaroo\Magento2\Test\BaseTest;

class AllOrSpecificCountriesTest extends BaseTest
{
    protected $instanceClass = AllOrSpecificCountries::class;

    public function testToOptionArray()
    {
        $expectedOptions = [
            'All Allowed Countries',
            'Specific Countries'
        ];

        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertCount(2, $result);

        foreach ($result as $option) {
            $this->assertTrue(in_array($option['label']->getText(), $expectedOptions));
        }
    }

    public function testToArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toArray();

        $this->assertEquals(__('All Allowed Countries'), $result[0]);
        $this->assertEquals(__('Specific Countries'), $result[1]);
    }
}
