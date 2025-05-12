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

class SimplifiedLog extends Logger implements BuckarooLoggerInterface
{
    private DebugConfiguration $debugConfiguration;

    protected array  $message = [];
    protected string $action  = '';

    public function __construct(
        DebugConfiguration $debugConfiguration,
        array $handlers   = [],
        array $processors = [],
        string $name      = 'buckaroo'
    ) {
        $this->debugConfiguration = $debugConfiguration;
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * Portable implementation that works with Monolog 2 **and** 3.
     */
    public function addRecord(
        mixed $level,
        string|\Stringable $message,
        array $context = [],
        \DateTimeInterface|null $datetime = null
    ): bool {
        if (! $this->debugConfiguration->canLog($level)) {
            return false;
        }

        // Convert an int to the enum if the project runs on Monolog 3.
        if (\class_exists(\Monolog\Level::class) && \is_int($level)) {
            /** @var \Monolog\Level $level */
            $level = \Monolog\Level::from($level);
        }

        $message = $this->action . (string) $message;

        return parent::addRecord($level, $message, $context, $datetime);
    }

    /* ---------------------------------------------------------------------
     * Convenience wrappers
     * -------------------------------------------------------------------*/
    public function addDebug(string $message): bool
    {
        return $this->addRecord(Logger::DEBUG, $message);
    }

    public function addError(string $message): bool
    {
        return $this->addRecord(Logger::ERROR, $message);
    }

    public function addWarning(string $message): bool
    {
        return $this->addRecord(Logger::WARNING, $message);
    }

    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }

    public function setAction(string $action): BuckarooLoggerInterface
    {
        $this->action = $action;
        return $this;
    }
}
