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
namespace TIG\Buckaroo\Test\Unit\Logging;

use Monolog\Logger;
use TIG\Buckaroo\Logging\DebugHandler;
use TIG\Buckaroo\Test\BaseTest;

class DebugHandlerTest extends BaseTest
{
    protected $instanceClass = DebugHandler::class;

    public function testLoggerType()
    {
        $instance = $this->getInstance();
        $property = $this->getProperty('loggerType', $instance);

        $this->assertEquals(Logger::DEBUG, $property);
    }

    public function testFileName()
    {
        $instance = $this->getInstance();
        $property = $this->getProperty('fileName', $instance);

        $this->assertEquals('/var/log/Buckaroo/debug.log', $property);
    }
}
