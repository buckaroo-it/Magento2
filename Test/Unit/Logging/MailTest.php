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
use TIG\Buckaroo\Logging\Mail;
use TIG\Buckaroo\Test\BaseTest;

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
            . '('. PHP_EOL
            . '    [0] => Message' . PHP_EOL
            . '    [1] => in an'. PHP_EOL
            . '    [2] => array'. PHP_EOL
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
