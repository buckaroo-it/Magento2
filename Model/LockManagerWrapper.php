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
namespace Buckaroo\Magento2\Model;

use Magento\Framework\Lock\LockManagerInterface;

class LockManagerWrapper
{
    /**
     * The lock manager interface instance.
     *
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * Lock prefix for uniqueness.
     */
    public const LOCK_PREFIX = 'buckaroo_lock_';

    /**
     * Constructor.
     *
     * @param LockManagerInterface $lockManager
     */
    public function __construct(LockManagerInterface $lockManager)
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Acquire a lock for a given order increment ID.
     *
     * @param string $order
     * @param int    $timeout
     *
     * @return bool
     */
    public function lockOrder(string $order, int $timeout = -1): bool
    {
        $lockName = $this->generateLockName($order);
        return $this->lockManager->lock($lockName, $timeout);
    }

    /**
     * Release the lock for a given order increment ID.
     *
     * @param string $order
     *
     * @return bool
     */
    public function unlockOrder(string $order): bool
    {
        $lockName = $this->generateLockName($order);
        return $this->lockManager->unlock($lockName);
    }

    /**
     * Check if a lock is set for a given order.
     *
     * @param string $order
     *
     * @return bool
     */
    public function isOrderLocked(string $order): bool
    {
        $lockName = $this->generateLockName($order);
        return $this->lockManager->isLocked($lockName);
    }

    /**
     * Generate a unique lock name for the push request.
     *
     * @param string $order
     *
     * @return string
     */
    protected function generateLockName(string $order): string
    {
        return self::LOCK_PREFIX . sha1($order);
    }
}
