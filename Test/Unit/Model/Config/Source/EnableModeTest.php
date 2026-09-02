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

use Buckaroo\Magento2\Model\Config\Source\Enablemode;
use Buckaroo\Magento2\Test\BaseTest;

class EnableModeTest extends BaseTest
{
    protected $instanceClass = Enablemode::class;

    /**
     * @var array
     */
    protected $expectedOptions = [
        'Off',
        'Test',
        'Live',
    ];

    public function testToOptionArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertCount(3, $result);

        foreach ($result as $option) {
            $this->assertTrue(in_array($option['label']->getText(), $this->expectedOptions));
        }
    }

    public function testToArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toArray();

        $this->assertCount(3, $result);

        foreach ($result as $option) {
            $this->assertTrue(in_array($option->getText(), $this->expectedOptions));
        }
    }
}
