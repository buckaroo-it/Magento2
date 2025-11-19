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

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Simple proxy around Monolog\Logger now that the class is final in v3.
 */
class InternalLogger implements LoggerInterface
{
    private $logger;

    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = []
    ) {
        $this->logger = new Logger($name, $handlers, $processors);
    }

    /* ---------------------------------------------------------------
     * Generic passthrough
     * ------------------------------------------------------------- */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->logger->log(LogLevel::EMERGENCY, $message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->logger->log(LogLevel::ALERT, $message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->logger->log(LogLevel::CRITICAL, $message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->logger->log(LogLevel::ERROR, $message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->logger->log(LogLevel::WARNING, $message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->logger->log(LogLevel::NOTICE, $message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->logger->log(LogLevel::DEBUG, $message, $context);
    }
}
