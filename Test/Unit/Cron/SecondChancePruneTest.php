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

namespace Buckaroo\Magento2\Test\Unit\Cron;

use Buckaroo\Magento2\Cron\SecondChancePrune;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\SecondChance\EnabledStoresProvider;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecondChancePruneTest extends TestCase
{
    /**
     * @var EnabledStoresProvider|\PHPUnit\Framework\MockObject\MockObject
     */
    private $enabledStoresProvider;

    /**
     * @var WorkChecker|\PHPUnit\Framework\MockObject\MockObject
     */
    private $workChecker;

    /**
     * @var Log|\PHPUnit\Framework\MockObject\MockObject
     */
    private $logging;

    /**
     * @var SecondChanceRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $secondChanceRepository;

    protected function setUp(): void
    {
        $this->enabledStoresProvider = $this->createMock(EnabledStoresProvider::class);
        $this->workChecker = $this->createMock(WorkChecker::class);
        $this->logging = $this->createMock(Log::class);
        $this->secondChanceRepository = $this->createMock(SecondChanceRepository::class);
    }

    public function testIdleRunDoesNotPruneOrLog(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $stores = [$store];

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn($stores);
        $this->workChecker->expects($this->once())
            ->method('hasPrunableItems')
            ->with($stores)
            ->willReturn(false);
        $this->secondChanceRepository->expects($this->never())->method('deleteOlderRecords');
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testLogsOnlyWhenRecordsWereDeleted(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([$store]);
        $this->workChecker->method('hasPrunableItems')->willReturn(true);
        $this->secondChanceRepository->method('deleteOlderRecords')->with($store)->willReturn(2);
        $this->logging->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('Pruned 2 old records for store: 1'));
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testConservativeGateDoesNotLogWhenStoreHasNoExpiredRecords(): void
    {
        $store = $this->createMock(StoreInterface::class);

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([$store]);
        $this->workChecker->method('hasPrunableItems')->willReturn(true);
        $this->secondChanceRepository->method('deleteOlderRecords')->with($store)->willReturn(0);
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->never())->method('addError');

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testPruneErrorRemainsLogged(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([$store]);
        $this->workChecker->method('hasPrunableItems')->willReturn(true);
        $this->secondChanceRepository->method('deleteOlderRecords')
            ->willThrowException(new RuntimeException('Delete failed'));
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Delete failed'));

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    public function testWorkCheckerErrorIsLogged(): void
    {
        $store = $this->createMock(StoreInterface::class);

        $this->enabledStoresProvider->method('getEnabledStores')->willReturn([$store]);
        $this->workChecker->method('hasPrunableItems')
            ->willThrowException(new RuntimeException('Count query failed'));
        $this->secondChanceRepository->expects($this->never())->method('deleteOlderRecords');
        $this->logging->expects($this->never())->method('addDebug');
        $this->logging->expects($this->once())
            ->method('addError')
            ->with($this->stringContains('Count query failed'));

        $instance = $this->createInstance();

        $this->assertSame($instance, $instance->execute());
    }

    private function createInstance(): SecondChancePrune
    {
        return new SecondChancePrune(
            $this->enabledStoresProvider,
            $this->workChecker,
            $this->logging,
            $this->secondChanceRepository
        );
    }
}
