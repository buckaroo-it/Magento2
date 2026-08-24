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
