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
