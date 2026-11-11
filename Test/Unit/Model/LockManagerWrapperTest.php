<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LockManagerWrapperTest extends TestCase
{
    private const ORDER = '300000320';

    /** @var LockManagerInterface|MockObject */
    private $lockManager;

    private LockManagerWrapper $wrapper;

    protected function setUp(): void
    {
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->wrapper = new LockManagerWrapper($this->lockManager);
    }

    public function testGatewayRequestMarkIsTakenWithoutWaiting(): void
    {
        $this->lockManager->expects($this->once())
            ->method('lock')
            ->with('buckaroo_gwreq_' . sha1(self::ORDER), 0)
            ->willReturn(true);

        $this->assertTrue($this->wrapper->markGatewayRequestStarted(self::ORDER));
    }

    public function testGatewayRequestMarkIsSeparateFromTheOrderLock(): void
    {
        $this->lockManager->expects($this->exactly(2))
            ->method('isLocked')
            ->willReturnCallback(fn (string $name) => $name === 'buckaroo_gwreq_' . sha1(self::ORDER));

        $this->assertTrue($this->wrapper->isGatewayRequestInProgress(self::ORDER));
        $this->assertFalse($this->wrapper->isOrderLocked(self::ORDER));
    }

    public function testGatewayRequestMarkIsReleasedByName(): void
    {
        $this->lockManager->expects($this->once())
            ->method('unlock')
            ->with('buckaroo_gwreq_' . sha1(self::ORDER))
            ->willReturn(true);

        $this->assertTrue($this->wrapper->markGatewayRequestFinished(self::ORDER));
    }

    public function testLockNamesFitMysqlGetLockLimit(): void
    {
        $this->lockManager->expects($this->exactly(2))
            ->method('lock')
            ->willReturnCallback(function (string $name) {
                $this->assertLessThanOrEqual(64, strlen($name));
                return true;
            });

        $this->wrapper->lockOrder(self::ORDER, 5);
        $this->wrapper->markGatewayRequestStarted(self::ORDER);
    }

    public function testOrderLockTakenAgainInTheSameProcessDoesNotWaitForItself(): void
    {
        $name = 'buckaroo_lock_' . sha1(self::ORDER);
        $this->lockManager->expects($this->once())->method('lock')->with($name, 5)->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock')->with($name)->willReturn(true);

        $this->assertTrue($this->wrapper->lockOrder(self::ORDER, 5));
        $this->assertTrue($this->wrapper->lockOrder(self::ORDER, 5), 'nested lock');
        $this->assertTrue($this->wrapper->unlockOrder(self::ORDER), 'nested unlock keeps the lock');
        $this->assertTrue($this->wrapper->unlockOrder(self::ORDER), 'outer unlock releases it');
    }

    public function testFailedOrderLockIsNotCountedAsHeld(): void
    {
        $this->lockManager->expects($this->exactly(2))
            ->method('lock')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->assertFalse($this->wrapper->lockOrder(self::ORDER, 1));
        $this->assertTrue($this->wrapper->lockOrder(self::ORDER, 1), 'next attempt asks the backend again');
    }

    public function testOrderLocksAreCountedPerOrder(): void
    {
        $this->lockManager->expects($this->exactly(2))->method('lock')->willReturn(true);
        $this->lockManager->expects($this->exactly(2))->method('unlock')->willReturn(true);

        $this->wrapper->lockOrder('100000001', 5);
        $this->wrapper->lockOrder('100000002', 5);
        $this->wrapper->unlockOrder('100000001');
        $this->wrapper->unlockOrder('100000002');
    }

    public function testUnlockWithoutLockStillAsksTheBackend(): void
    {
        $this->lockManager->expects($this->once())->method('unlock')->willReturn(false);

        $this->assertFalse($this->wrapper->unlockOrder(self::ORDER));
    }
}
