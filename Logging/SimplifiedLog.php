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
use Monolog\JsonSerializableDateTimeImmutable;
use Monolog\Level;
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
     * @var string
     */
    protected string $action = '';

    /**
     * SimplifiedLog constructor.
     *
     * @param DebugConfiguration $debugConfiguration
     * @param HandlerInterface[] $handlers
     * @param callable[]         $processors
     * @param string             $name
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
     * {@inheritDoc}
     *
     * Updated for Monolog 3.x compatibility (Magento 2.4.8+).
     */
    public function addRecord(
        Level|int $level,
        string|\Stringable $message,
        array $context = [],
        ?JsonSerializableDateTimeImmutable $datetime = null
    ): bool {
        if (! $this->debugConfiguration->canLog($level)) {
            return false;
        }

        $message = $this->action . (string) $message;

        // Forward to parent with updated signature
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

    /**
     * {@inheritDoc}
     */
    public function debug($message, array $context = []): void
    {
        $this->addRecord(Logger::DEBUG, (string) $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function setAction(string $action): BuckarooLoggerInterface
    {
        $this->action = $action;
        return $this;
    }
}
