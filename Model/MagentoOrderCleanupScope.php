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

namespace Buckaroo\Magento2\Model;

/**
 * Tracks whether Magento's expired-order cleanup cron is the one currently cancelling orders.
 *
 * A cancellation carries no record of who asked for it, so a guard that must treat the cleanup cron
 * differently from an administrator or an incoming push has no way to tell them apart on its own.
 */
class MagentoOrderCleanupScope
{
    /**
     * @var bool
     */
    private bool $running = false;

    /**
     * Whether the expired-order cleanup cron is running right now.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Run the callback with the cleanup cron marked as the active caller.
     *
     * The previous value is restored rather than cleared, so a nested call cannot end the scope
     * early, and it is restored even when the callback throws.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function run(callable $callback)
    {
        $previous = $this->running;
        $this->running = true;

        try {
            return $callback();
        } finally {
            $this->running = $previous;
        }
    }
}
