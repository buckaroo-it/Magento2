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

namespace Buckaroo\Magento2\Test\Helper;

use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Helper\Data;

class DataTest extends BaseTest
{
    protected $instanceClass = Data::class;

    public function testGetStatusCode()
    {
        $instance = $this->getInstance();
        $this->assertNull($instance->getStatusCode(''));

        foreach ($instance->getStatusCodes() as $name => $code) {
            $this->assertEquals($code, $instance->getStatusCode($name));
        }
    }

    public function testGetStatusByValue()
    {
        $instance = $this->getInstance();
        $this->assertNull($instance->getStatusByValue(''));

        foreach ($instance->getStatusCodes() as $name => $code) {
            $this->assertEquals($name, $instance->getStatusByValue($code));
        }
    }

    public function testGetStatusCodes()
    {
        $instance = $this->getInstance();
        $this->assertNotEquals(0, count($instance->getStatusCodes()));
    }
}
