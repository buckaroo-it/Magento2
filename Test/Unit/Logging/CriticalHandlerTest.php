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

namespace Buckaroo\Magento2\Test\Unit\Logging;

use Monolog\Logger;
use Buckaroo\Magento2\Logging\CriticalHandler;
use Buckaroo\Magento2\Test\BaseTest;

class CriticalHandlerTest extends BaseTest
{
    protected $instanceClass = CriticalHandler::class;

    public function testLoggerType()
    {
        $instance = $this->getInstance();
        $property = $this->getProperty('loggerType', $instance);

        $this->assertEquals(Logger::CRITICAL, $property);
    }

    public function testFileName()
    {
        $instance = $this->getInstance();
        $property = $this->getProperty('fileName', $instance);

        $this->assertEquals('/var/log/Buckaroo/critical.log', $property);
    }
}
