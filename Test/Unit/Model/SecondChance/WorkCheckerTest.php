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

namespace Buckaroo\Magento2\Test\Unit\Model\SecondChance;

use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Buckaroo\Magento2\Model\SecondChance\RecordsQuery;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class WorkCheckerTest extends TestCase
{
    private const NOW = 1700000000;

    /**
     * @var RecordsQuery|\PHPUnit\Framework\MockObject\MockObject
     */
    private $recordsQuery;

    /**
     * @var SecondChanceConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configProvider;

    /**
     * @var DateTime|\PHPUnit\Framework\MockObject\MockObject
     */
    private $dateTime;

    protected function setUp(): void
    {
        $this->recordsQuery = $this->createMock(RecordsQuery::class);
        $this->configProvider = $this->createMock(SecondChanceConfig::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->dateTime->method('gmtTimestamp')->willReturn(self::NOW);
        $this->dateTime->method('gmtDate')
            ->willReturnCallback(
                function ($format, $timestamp): string {
                    return gmdate($format ?? 'Y-m-d H:i:s', $timestamp);
                }
            );
    }

    public function testIdleEmailRunUsesTwoGlobalQueriesAndReturnsFalse(): void
    {
        $store = $this->createStore(5);
        $filters = [];

        $this->configProvider->method('isSecondEmailEnabled')->with($store)->willReturn(true);
        $this->configProvider->method('isFirstEmailEnabled')->with($store)->willReturn(true);
        $this->configProvider->method('getSecondChanceDelay')
            ->willReturnCallback(
                function ($step): int {
                    return $step === 2 ? 2 : 1;
                }
            );
        $this->recordsQuery->expects($this->exactly(2))
            ->method('hasRecords')
            ->willReturnCallback(
                function ($queryFilters) use (&$filters): bool {
                    $filters[] = $queryFilters;
                    return false;
                }
            );

        $this->assertFalse($this->createChecker()->hasProcessableItems([$store]));
        $this->assertSame([
            [
                'store_id' => ['in' => [5]],
                'status' => 'step1_sent',
                'first_email_sent' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - 7200)],
            ],
            [
                'store_id' => ['in' => [5]],
                'status' => 'pending',
                'created_at' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - 3600)],
            ],
        ], $filters);
    }

    public function testEmailGateKeepsStoreSpecificDelays(): void
    {
        $storeOne = $this->createStore(1);
        $storeTwo = $this->createStore(2);
        $filters = [];

        $this->configProvider->method('isSecondEmailEnabled')->willReturn(false);
        $this->configProvider->method('isFirstEmailEnabled')->willReturn(true);
        $this->configProvider->method('getSecondChanceDelay')
            ->willReturnCallback(
                function ($step, $store) use ($storeOne): int {
                    return $step === 1 && $store === $storeOne ? 1 : 24;
                }
            );
        $this->recordsQuery->expects($this->exactly(2))
            ->method('hasRecords')
            ->willReturnCallback(
                function ($queryFilters) use (&$filters): bool {
                    $filters[] = $queryFilters;
                    return false;
                }
            );

        $this->assertFalse($this->createChecker()->hasProcessableItems([$storeOne, $storeTwo]));
        $this->assertSame([
            [
                'store_id' => ['in' => [1]],
                'status' => 'pending',
                'created_at' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - 3600)],
            ],
            [
                'store_id' => ['in' => [2]],
                'status' => 'pending',
                'created_at' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - 86400)],
            ],
        ], $filters);
    }

    public function testProcessableSecondStepAvoidsAnotherCountQuery(): void
    {
        $store = $this->createStore(4);
        $expectedFilters = [
            'store_id' => ['in' => [4]],
            'status' => 'step1_sent',
            'first_email_sent' => ['lteq' => gmdate('Y-m-d H:i:s', self::NOW)],
        ];

        $this->configProvider->method('isSecondEmailEnabled')->with($store)->willReturn(true);
        $this->configProvider->method('getSecondChanceDelay')->with(2, $store)->willReturn(0);
        $this->configProvider->expects($this->never())->method('isFirstEmailEnabled');
        $this->recordsQuery->expects($this->once())
            ->method('hasRecords')
            ->with($expectedFilters)
            ->willReturn(true);

        $this->assertTrue($this->createChecker()->hasProcessableItems([$store]));
    }

    public function testPruneGateKeepsStoreSpecificRetentionPeriods(): void
    {
        $storeOne = $this->createStore(1);
        $storeTwo = $this->createStore(2);
        $filters = [];

        $this->configProvider->method('getSecondChanceDeleteAfterDays')
            ->willReturnCallback(
                function ($store) use ($storeOne): int {
                    return $store === $storeOne ? 7 : 30;
                }
            );
        $this->recordsQuery->expects($this->exactly(2))
            ->method('hasRecords')
            ->willReturnCallback(
                function ($queryFilters) use (&$filters): bool {
                    $filters[] = $queryFilters;
                    return false;
                }
            );

        $this->assertFalse($this->createChecker()->hasPrunableItems([$storeOne, $storeTwo]));
        $this->assertSame([
            [
                'store_id' => ['in' => [1]],
                'created_at' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - (7 * 86400))],
            ],
            [
                'store_id' => ['in' => [2]],
                'created_at' => ['lt' => gmdate('Y-m-d H:i:s', self::NOW - (30 * 86400))],
            ],
        ], $filters);
    }

    public function testPruneGateSkipsQueryWhenRetentionIsDisabled(): void
    {
        $store = $this->createStore(1);

        $this->configProvider->method('getSecondChanceDeleteAfterDays')->with($store)->willReturn(0);
        $this->recordsQuery->expects($this->never())->method('hasRecords');

        $this->assertFalse($this->createChecker()->hasPrunableItems([$store]));
    }

    /**
     * @param int $storeId
     * @return StoreInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createStore(int $storeId)
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    private function createChecker(): WorkChecker
    {
        return new WorkChecker(
            $this->recordsQuery,
            $this->configProvider,
            $this->dateTime
        );
    }
}
