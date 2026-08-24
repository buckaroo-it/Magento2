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

namespace Buckaroo\Magento2\Test\Unit\Model\ResourceModel\SecondChance;

use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The collection owns the SecondChance selection rules, so these tests pin the exact filters
 * that both the cron gate and the processing path depend on.
 */
class CollectionTest extends TestCase
{
    private const NOW = 1700000000;

    /**
     * Filters applied to the collection under test.
     *
     * @var array
     */
    private $filters = [];

    protected function setUp(): void
    {
        $this->filters = [];
    }

    public function testStoreFilterAcceptsASingleIdAndCastsIt(): void
    {
        $this->createCollection()->addStoreFilter('7');

        $this->assertSame([['store_id', ['in' => [7]]]], $this->filters);
    }

    public function testStoreFilterAcceptsMultipleIds(): void
    {
        $this->createCollection()->addStoreFilter([1, 3]);

        $this->assertSame([['store_id', ['in' => [1, 3]]]], $this->filters);
    }

    public function testFirstStepSelectsPendingRecordsByCreationDate(): void
    {
        $this->createCollection()->addStepDueFilter(Collection::STEP_FIRST_EMAIL, 2);

        $this->assertSame(
            [
                ['status', 'pending'],
                ['created_at', ['lt' => $this->cutoff(2 * 3600)]],
            ],
            $this->filters
        );
    }

    public function testSecondStepSelectsSentRecordsByFirstEmailDate(): void
    {
        $this->createCollection()->addStepDueFilter(Collection::STEP_SECOND_EMAIL, 24);

        $this->assertSame(
            [
                ['status', 'step1_sent'],
                ['first_email_sent', ['lt' => $this->cutoff(24 * 3600)]],
            ],
            $this->filters
        );
    }

    public function testZeroDelayMakesTheCutoffInclusive(): void
    {
        $this->createCollection()->addStepDueFilter(Collection::STEP_FIRST_EMAIL, 0);

        $this->assertSame(
            [
                ['status', 'pending'],
                ['created_at', ['lteq' => $this->cutoff(0)]],
            ],
            $this->filters
        );
    }

    public function testNegativeDelayIsTreatedAsImmediate(): void
    {
        $this->createCollection()->addStepDueFilter(Collection::STEP_FIRST_EMAIL, -5);

        $this->assertSame(
            [
                ['status', 'pending'],
                ['created_at', ['lteq' => $this->cutoff(0)]],
            ],
            $this->filters
        );
    }

    public function testRetentionFilterUsesADayWindow(): void
    {
        $this->createCollection()->addCreatedBeforeFilter(30);

        $this->assertSame(
            [['created_at', ['lt' => $this->cutoff(30 * 86400)]]],
            $this->filters
        );
    }

    public function testRemovableFilterProtectsRecordsStillAwaitingAReminder(): void
    {
        $this->createCollection()->addRemovableFilter(1, 96);

        $this->assertSame(
            [
                ['created_at', ['lt' => $this->cutoff(1 * 86400)]],
                [
                    ['status', 'created_at'],
                    [
                        ['nin' => ['pending', 'step1_sent']],
                        ['lt' => $this->cutoff(96 * 3600)],
                    ],
                ],
            ],
            $this->filters
        );
    }

    public function testRemovableFilterWithoutRemindersStillProtectsNothingExtra(): void
    {
        $this->createCollection()->addRemovableFilter(30, 0);

        $this->assertSame(
            [
                ['created_at', ['lt' => $this->cutoff(30 * 86400)]],
                [
                    ['status', 'created_at'],
                    [
                        ['nin' => ['pending', 'step1_sent']],
                        ['lt' => $this->cutoff(0)],
                    ],
                ],
            ],
            $this->filters
        );
    }

    public function testFiltersAreChainable(): void
    {
        $collection = $this->createCollection();

        $this->assertSame($collection, $collection->addStoreFilter(1));
        $this->assertSame($collection, $collection->addStepDueFilter(Collection::STEP_FIRST_EMAIL, 1));
        $this->assertSame($collection, $collection->addCreatedBeforeFilter(1));
        $this->assertSame($collection, $collection->addRemovableFilter(1, 1));
    }

    /**
     * @param int $seconds
     * @return string
     */
    private function cutoff(int $seconds): string
    {
        return gmdate('Y-m-d H:i:s', self::NOW - $seconds);
    }

    /**
     * Build a collection with the database plumbing replaced by a filter recorder.
     *
     * @return Collection|MockObject
     */
    private function createCollection()
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter'])
            ->getMock();

        $collection->method('addFieldToFilter')
            ->willReturnCallback(
                function ($field, $condition = null) use ($collection) {
                    $this->filters[] = [$field, $condition];
                    return $collection;
                }
            );

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);
        $dateTime->method('gmtDate')
            ->willReturnCallback(
                function ($format, $timestamp): string {
                    return gmdate($format ?? 'Y-m-d H:i:s', $timestamp);
                }
            );

        $property = new \ReflectionProperty(Collection::class, 'dateTime');
        $property->setAccessible(true);
        $property->setValue($collection, $dateTime);

        return $collection;
    }
}
