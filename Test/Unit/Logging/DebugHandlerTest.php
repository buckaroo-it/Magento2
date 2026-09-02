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
use Buckaroo\Magento2\Logging\DebugHandler;
use Buckaroo\Magento2\Test\BaseTest;

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

        $this->assertMatchesRegularExpression('/\/var\/log\/Buckaroo\/\d{4}-\d{2}-\d{2}\.log/', $property);
    }
}
