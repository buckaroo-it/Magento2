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

use Buckaroo\Magento2\Model\Config\Source\PaypalButtonShape;
use Buckaroo\Magento2\Test\BaseTest;

class PaypalButtonShapeTest extends BaseTest
{
    protected $instanceClass = PaypalButtonShape::class;

    /**
     * @var array
     */
    protected $expectedOptions = [
        'Rectangular',
        'Rounded',
    ];

    public function testToOptionArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertCount(2, $result);
        $this->assertSame(PaypalButtonShape::SHAPE_RECTANGULAR, $result[0]['value']);
        $this->assertSame(PaypalButtonShape::SHAPE_ROUNDED, $result[1]['value']);

        foreach ($result as $option) {
            $this->assertTrue(in_array($option['label']->getText(), $this->expectedOptions));
        }
    }

    public function testToArray()
    {
        $instance = $this->getInstance();
        $result = $instance->toArray();

        $this->assertCount(2, $result);
        $this->assertEquals(__('Rectangular'), $result[PaypalButtonShape::SHAPE_RECTANGULAR]);
        $this->assertEquals(__('Rounded'), $result[PaypalButtonShape::SHAPE_ROUNDED]);
    }
}
