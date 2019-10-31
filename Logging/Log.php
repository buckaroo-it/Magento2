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
namespace TIG\Buckaroo\Logging;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use TIG\Buckaroo\Model\ConfigProvider\DebugConfiguration;

class Log extends Logger
{
    /** @var DebugConfiguration */
    private $debugConfiguration;

    /** @var Mail */
    private $mail;

    /** @var array */
    protected $message = [];

    /**
     * Log constructor.
     *
     * @param string             $name
     * @param DebugConfiguration $debugConfiguration
     * @param Mail               $mail
     * @param HandlerInterface[] $handlers
     * @param callable[]         $processors
     */
    public function __construct(
        $name,
        DebugConfiguration $debugConfiguration,
        Mail $mail,
        array $handlers = [],
        array $processors = []
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->mail = $mail;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Make sure the debug information is always send to the debug email
     */
    public function __destruct()
    {
        $this->mail->mailMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function addRecord($level, $message, array $context = [])
    {
        if (!$this->debugConfiguration->canLog($level)) {
            return false;
        }

        // Prepare the message to be send to the debug email
        $this->mail->addToMessage($message);

        return parent::addRecord($level, $message, $context);
    }
}
