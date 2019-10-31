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
namespace TIG\Buckaroo\Test\Helper;

use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Helper\Data;

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
