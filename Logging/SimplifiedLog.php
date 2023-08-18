<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Logging;

use Buckaroo\Magento2\Model\ConfigProvider\DebugConfiguration;
use Monolog\DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class SimplifiedLog extends Logger implements BuckarooLoggerInterface
{
    /**
     * @var DebugConfiguration
     */
    private DebugConfiguration $debugConfiguration;

    /**
     * @var array
     */
    protected array $message = [];

    /**
     * Log constructor.
     *
     * @param DebugConfiguration $debugConfiguration
     * @param HandlerInterface[] $handlers
     * @param callable[] $processors
     * @param string $name
     */
    public function __construct(
        DebugConfiguration $debugConfiguration,
        array $handlers = [],
        array $processors = [],
        string $name = 'buckaroo'
    ) {
        $this->debugConfiguration = $debugConfiguration;

        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @inheritdoc
     */
    public function addRecord(
        int $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        if (!$this->debugConfiguration->canLog($level)) {
            return false;
        }

        return parent::addRecord($level, $message, $context);
    }

    /**
     * Logs a debug message.
     *
     * @param string $message
     * @return bool
     */
    public function addDebug(string $message): bool
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }

    /**
     * Logs an error message.
     *
     * @param string $message
     * @return bool
     */
    public function addError(string $message): bool
    {
        return $this->addRecord(Logger::ERROR, $message);
    }

    /**
     * Logs a warning message.
     *
     * @param string $message
     * @return bool
     */
    public function addWarning(string $message): bool
    {
        return $this->addRecord(Logger::WARNING, $message);
    }

    /**
     * @inheritdoc
     */
    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }
}
