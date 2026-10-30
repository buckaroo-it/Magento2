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
