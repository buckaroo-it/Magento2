<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world‑wide‑web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world‑wide‑web, please email
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
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Log implements BuckarooLoggerInterface
{
    private DebugConfiguration $debugConfiguration;
    private Logger             $logger;

    public function __construct(
        DebugConfiguration $debugConfiguration,
        array $handlers   = [],
        array $processors = [],
        string $name      = 'buckaroo'
    ) {
        $this->debugConfiguration = $debugConfiguration;
        $this->logger             = new Logger($name, $handlers, $processors);
    }

    /* ---------------------------------------------------------------------
     * Portable addRecord()
     * -------------------------------------------------------------------*/
    public function addRecord(
        mixed $level,
        \Stringable|string $message,
        array $context = [],
        \DateTimeInterface|null $datetime = null
    ): bool {
        if (! $this->debugConfiguration->canLog($level)) {
            return false;
        }

        if (\class_exists(\Monolog\Level::class) && \is_int($level)) {
            /** @var \Monolog\Level $level */
            $level = \Monolog\Level::from($level);
        }

        return $this->logger->addRecord($level, (string) $message, $context, $datetime);
    }

    /* ----------------------------------------------------------------- */
    public function addDebug(string $message): bool   { return $this->addRecord(Logger::DEBUG,   $message); }
    public function addError(string $message): bool   { return $this->addRecord(Logger::ERROR,   $message); }
    public function addWarning(string $message): bool { return $this->addRecord(Logger::WARNING, $message); }

    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }

    public function setAction(string $action): BuckarooLoggerInterface
    {
        /* kept for interface compatibility, no-op in this implementation */
        return $this;
    }
}
