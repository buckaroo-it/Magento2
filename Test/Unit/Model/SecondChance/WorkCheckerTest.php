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
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory;
use Buckaroo\Magento2\Model\SecondChance\WorkChecker;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WorkCheckerTest extends TestCase
{
    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    /**
     * @var SecondChanceConfig|MockObject
     */
    private $configProvider;

    /**
     * Filter calls recorded from every collection the factory handed out.
     *
     * @var array
     */
    private $queries = [];

    protected function setUp(): void
    {
        $this->queries = [];
        $this->configProvider = $this->createMock(SecondChanceConfig::class);
        $this->collectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
    }

    public function testIdleEmailRunQueriesBothStepsAndReturnsFalse(): void
    {
        $store = $this->createStore(5);

        $this->configProvider->method('isEmailStepEnabled')->willReturn(true);
        $this->configProvider->method('getSecondChanceDelay')
            ->willReturnCallback(
                function ($step): int {
                    return $step === Collection::STEP_SECOND_EMAIL ? 2 : 1;
                }
            );
        $this->expectCollections([false, false]);

        $this->assertFalse($this->createChecker()->hasProcessableItems([$store]));
        $this->assertSame(
            [
                ['store' => [5], 'step' => [Collection::STEP_SECOND_EMAIL, 2]],
                ['store' => [5], 'step' => [Collection::STEP_FIRST_EMAIL, 1]],
            ],
            $this->queries
        );
    }

    public function testStoresSharingADelayAreCountedInOneQuery(): void
    {
        $storeOne = $this->createStore(1);
        $storeTwo = $this->createStore(2);

        $this->configProvider->method('isEmailStepEnabled')
            ->willReturnCallback(
                function ($step): bool {
                    return $step === Collection::STEP_FIRST_EMAIL;
                }
            );
        $this->configProvider->method('getSecondChanceDelay')->willReturn(4);
        $this->expectCollections([false]);

        $this->assertFalse($this->createChecker()->hasProcessableItems([$storeOne, $storeTwo]));
        $this->assertSame(
            [['store' => [1, 2], 'step' => [Collection::STEP_FIRST_EMAIL, 4]]],
            $this->queries
        );
    }

    public function testStoresWithDifferentDelaysAreQueriedSeparately(): void
    {
        $storeOne = $this->createStore(1);
        $storeTwo = $this->createStore(2);

        $this->configProvider->method('isEmailStepEnabled')
            ->willReturnCallback(
                function ($step): bool {
                    return $step === Collection::STEP_FIRST_EMAIL;
                }
            );
        $this->configProvider->method('getSecondChanceDelay')
            ->willReturnCallback(
                function ($step, $store) use ($storeOne): int {
                    return $store === $storeOne ? 1 : 24;
                }
            );
        $this->expectCollections([false, false]);

        $this->assertFalse($this->createChecker()->hasProcessableItems([$storeOne, $storeTwo]));
        $this->assertSame(
            [
                ['store' => [1], 'step' => [Collection::STEP_FIRST_EMAIL, 1]],
                ['store' => [2], 'step' => [Collection::STEP_FIRST_EMAIL, 24]],
            ],
            $this->queries
        );
    }

    public function testWorkFoundInSecondStepSkipsTheFirstStepQuery(): void
    {
        $store = $this->createStore(4);

        $this->configProvider->method('isEmailStepEnabled')->willReturn(true);
        $this->configProvider->method('getSecondChanceDelay')->willReturn(0);
        $this->expectCollections([true]);

        $this->assertTrue($this->createChecker()->hasProcessableItems([$store]));
        $this->assertSame(
            [['store' => [4], 'step' => [Collection::STEP_SECOND_EMAIL, 0]]],
            $this->queries
        );
    }

    public function testDisabledEmailStepIsNeverQueried(): void
    {
        $store = $this->createStore(7);

        $this->configProvider->method('isEmailStepEnabled')->willReturn(false);
        $this->configProvider->expects($this->never())->method('getSecondChanceDelay');
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertFalse($this->createChecker()->hasProcessableItems([$store]));
    }

    public function testPruneGateGroupsStoresByRetentionWindow(): void
    {
        $storeOne = $this->createStore(1);
        $storeTwo = $this->createStore(2);
        $storeThree = $this->createStore(3);

        $this->configProvider->method('isRecordPruningEnabled')->willReturn(true);
        $this->configProvider->method('getSecondChanceDeleteAfterDays')
            ->willReturnCallback(
                function ($store) use ($storeTwo): int {
                    return $store === $storeTwo ? 90 : 30;
                }
            );
        $this->configProvider->method('getReminderWindowHours')
            ->willReturnCallback(
                function ($store) use ($storeThree): int {
                    return $store === $storeThree ? 96 : 24;
                }
            );
        $this->expectCollections([false, false]);

        $this->assertFalse($this->createChecker()->hasPrunableItems([$storeOne, $storeTwo, $storeThree]));
        $this->assertSame(
            [
                ['store' => [1, 3], 'removable' => [30, 96]],
                ['store' => [2], 'removable' => [90, 24]],
            ],
            $this->queries
        );
    }

    public function testPruneGateSkipsStoresWithPruningDisabled(): void
    {
        $store = $this->createStore(8);

        $this->configProvider->method('isRecordPruningEnabled')->willReturn(false);
        $this->configProvider->expects($this->never())->method('getSecondChanceDeleteAfterDays');
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertFalse($this->createChecker()->hasPrunableItems([$store]));
    }

    public function testEmptyStoreListNeverQueries(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertFalse($this->createChecker()->hasProcessableItems([]));
        $this->assertFalse($this->createChecker()->hasPrunableItems([]));
    }

    /**
     * Queue one collection per expected query, each reporting whether it found rows.
     *
     * @param bool[] $results
     * @return void
     */
    private function expectCollections(array $results): void
    {
        $collections = [];
        foreach ($results as $hasRows) {
            $collections[] = $this->createCollection($hasRows);
        }

        $this->collectionFactory->expects($this->exactly(count($results)))
            ->method('create')
            ->willReturnOnConsecutiveCalls(...$collections);
    }

    /**
     * Build a collection that records the filters applied to it.
     *
     * @param bool $hasRows
     * @return Collection|MockObject
     */
    private function createCollection(bool $hasRows)
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addStoreFilter', 'addStepDueFilter', 'addRemovableFilter', 'getSize'])
            ->getMock();

        $this->queries[] = [];
        $index = array_key_last($this->queries);

        $collection->method('addStoreFilter')
            ->willReturnCallback(
                function ($storeIds) use ($collection, $index) {
                    $this->queries[$index]['store'] = $storeIds;
                    return $collection;
                }
            );
        $collection->method('addStepDueFilter')
            ->willReturnCallback(
                function ($step, $delay) use ($collection, $index) {
                    $this->queries[$index]['step'] = [$step, $delay];
                    return $collection;
                }
            );
        $collection->method('addRemovableFilter')
            ->willReturnCallback(
                function ($days, $reminderWindowHours) use ($collection, $index) {
                    $this->queries[$index]['removable'] = [$days, $reminderWindowHours];
                    return $collection;
                }
            );
        $collection->method('getSize')->willReturn($hasRows ? 1 : 0);

        return $collection;
    }

    /**
     * @param int $storeId
     * @return StoreInterface|MockObject
     */
    private function createStore(int $storeId)
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    /**
     * @return WorkChecker
     */
    private function createChecker(): WorkChecker
    {
        return new WorkChecker($this->collectionFactory, $this->configProvider);
    }
}
