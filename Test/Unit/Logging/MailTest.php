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
use Buckaroo\Magento2\Logging\Mail;
use Buckaroo\Magento2\Test\BaseTest;

class MailTest extends BaseTest
{
    protected $instanceClass = Mail::class;

    public function testGetMessage()
    {
        $testMessage = 'Test Message';

        /** @var Mail $instance */
        $instance = $this->getInstance();
        $instance->addToMessage($testMessage);

        $result = $instance->getMessage();
        $this->assertEquals([$testMessage], $result);
    }

    public function testGetMessageAsString()
    {
        $testMessage = 'Test Message';
        $arrayMessage = ['Message', 'in an', 'array'];
        $expectedMessage = 'Test Message' . PHP_EOL
            . 'Array' . PHP_EOL
            . '(' . PHP_EOL
            . '    [0] => Message' . PHP_EOL
            . '    [1] => in an' . PHP_EOL
            . '    [2] => array' . PHP_EOL
            . ')' . PHP_EOL;

        /** @var Mail $instance */
        $instance = $this->getInstance();
        $instance->addToMessage($testMessage);
        $instance->addToMessage($arrayMessage);

        $result = $instance->getMessageAsString();
        $this->assertEquals($expectedMessage, $result);
    }

    public function testResetMessage()
    {
        $testMessage = 'Message to be deleted';

        /** @var Mail $instance */
        $instance = $this->getInstance();
        $instance->addToMessage($testMessage);
        $instance->resetMessage();

        $result = $instance->getMessage();
        $this->assertCount(0, $result);
    }

    public function testGetMailSubject()
    {
        $testSubject = 'Mail Subject';

        /** @var Mail $instance */
        $instance = $this->getInstance();
        $instance->setMailSubject($testSubject);

        $result = $instance->getMailSubject();
        $this->assertEquals($testSubject, $result);
    }

    public function testGetMailFrom()
    {
        $testMailFrom = 'Mail From';

        /** @var Mail $instance */
        $instance = $this->getInstance();
        $instance->setMailFrom($testMailFrom);

        $result = $instance->getMailFrom();
        $this->assertEquals($testMailFrom, $result);
    }
}
