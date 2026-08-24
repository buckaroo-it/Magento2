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

use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory;
use Buckaroo\Magento2\Model\SecondChance\RecordsQuery;
use PHPUnit\Framework\TestCase;

class RecordsQueryTest extends TestCase
{
    public function testAppliesAllFiltersAndReturnsTrueWhenRecordsExist(): void
    {
        $filters = [
            'store_id' => ['in' => [1, 2]],
            'status' => 'pending',
        ];
        $appliedFilters = [];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->willReturnCallback(
                function ($field, $condition) use (&$appliedFilters, $collection) {
                    $appliedFilters[$field] = $condition;
                    return $collection;
                }
            );
        $collection->expects($this->once())->method('getSize')->willReturn(1);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $this->assertTrue((new RecordsQuery($collectionFactory))->hasRecords($filters));
        $this->assertSame($filters, $appliedFilters);
    }

    public function testReturnsFalseForAnEmptyCollection(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->never())->method('addFieldToFilter');
        $collection->expects($this->once())->method('getSize')->willReturn(0);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $this->assertFalse((new RecordsQuery($collectionFactory))->hasRecords([]));
    }
}
