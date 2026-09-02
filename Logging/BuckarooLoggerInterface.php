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
declare(strict_types=1);

namespace Buckaroo\Magento2\Logging;

interface BuckarooLoggerInterface
{
    /**
     * Add a debug-level message to the log
     *
     * @param string $message
     *
     * @return bool
     */
    public function addDebug(string $message): bool;

    /**
     * Add an error-level message to the log
     *
     * @param string $message
     *
     * @return bool
     */
    public function addError(string $message): bool;

    /**
     * Add a warning-level message to the log
     *
     * @param string $message
     *
     * @return bool
     */
    public function addWarning(string $message): bool;

    /**
     * Log a debug message with optional context
     *
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, array $context = []): void;

    /**
     * Set the action name used to prefix log messages
     *
     * @param string $action
     *
     * @return BuckarooLoggerInterface
     */
    public function setAction(string $action): BuckarooLoggerInterface;
}
