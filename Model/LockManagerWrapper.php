<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
     * How many times this process holds each order lock, by lock name.
     *
     * A push that holds the order lock can start a command for the same order, for example the
     * cancellation of a Klarna reservation. MySQL GET_LOCK lets the same connection take a lock again,
     * the cache, file and Zookeeper lock backends do not: the command would wait for the lock its own
     * process holds and fail when the wait runs out. Counting here makes every backend behave alike.
     *
     * @var array<string, int>
     */
    private array $heldOrderLocks = [];

    /**
     * Lock prefix for uniqueness.
     */
    public const LOCK_PREFIX = 'buckaroo_lock_';

    /**
     * Lock prefix for the mark of a request to Buckaroo in progress.
     */
    public const GATEWAY_REQUEST_LOCK_PREFIX = 'buckaroo_gwreq_';

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
        if (isset($this->heldOrderLocks[$lockName])) {
            $this->heldOrderLocks[$lockName]++;
            return true;
        }

        if (!$this->lockManager->lock($lockName, $timeout)) {
            return false;
        }

        $this->heldOrderLocks[$lockName] = 1;
        return true;
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
        if (isset($this->heldOrderLocks[$lockName]) && --$this->heldOrderLocks[$lockName] > 0) {
            return true;
        }

        unset($this->heldOrderLocks[$lockName]);
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
     * Mark that a request to Buckaroo for this order is waiting for its response.
     *
     * Buckaroo sends the push for a transaction before it answers the request that started it, and
     * waits for the push response. A push that waited for the order lock held by that request would
     * wait for its own transaction. The mark lets the push see this and answer at once, so Buckaroo
     * answers the request and sends the push again later.
     *
     * Only release the mark when this returned true: a nested request in the same process can fail
     * to take it while the outer request still holds it.
     *
     * @param string $order
     *
     * @return bool
     */
    public function markGatewayRequestStarted(string $order): bool
    {
        return $this->lockManager->lock($this->generateGatewayRequestLockName($order), 0);
    }

    /**
     * Remove the mark set by markGatewayRequestStarted().
     *
     * @param string $order
     *
     * @return bool
     */
    public function markGatewayRequestFinished(string $order): bool
    {
        return $this->lockManager->unlock($this->generateGatewayRequestLockName($order));
    }

    /**
     * Whether a request to Buckaroo for this order is waiting for its response.
     *
     * @param string $order
     *
     * @return bool
     */
    public function isGatewayRequestInProgress(string $order): bool
    {
        return $this->lockManager->isLocked($this->generateGatewayRequestLockName($order));
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

    /**
     * Generate the lock name that marks a request to Buckaroo in progress for the order.
     *
     * @param string $order
     *
     * @return string
     */
    protected function generateGatewayRequestLockName(string $order): string
    {
        return self::GATEWAY_REQUEST_LOCK_PREFIX . sha1($order);
    }
}
