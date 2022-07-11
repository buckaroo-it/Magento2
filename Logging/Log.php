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
namespace Buckaroo\Magento2\Logging;

use Monolog\Logger;
use Monolog\Handler\HandlerInterface;
use Buckaroo\Magento2\Logging\InternalLogger;
use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;

class Log 
{
    /** @var DebugConfiguration */
    private $debugConfiguration;

    /** @var Mail */
    private $mail;

    /** @var array */
    protected $message = [];

    private static $processUid = 0;

    /**
     * @var \Buckaroo\Magento2\Logging\InternalLogger
     */
    private $logger;

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
        DebugConfiguration $debugConfiguration,
        Mail $mail,
        InternalLogger $logger
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->mail = $mail;
        $this->logger = $logger;

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
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        if (!$this->debugConfiguration->canLog($level)) {
            return false;
        }

        if (empty(self::$processUid)) {
            self::$processUid = uniqid();
        }

        $message = self::$processUid . '|' . microtime(true). '|' . $message;

        // Prepare the message to be send to the debug email
        $this->mail->addToMessage($message);

        return $this->logger->addRecord($level, $message, $context);
    }

    /**
     * @param string $message
     * @return bool
     */
    public function addDebug(string $message): bool
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }

    public function addError(string $message): bool
    {
        return $this->addRecord(Logger::ERROR, $message);
    }
    /**
     * {@inheritdoc}
     */
    public function debug($message)
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }
}
