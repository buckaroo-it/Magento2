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

namespace Buckaroo\Magento2\Test\Unit\Model\ResourceModel;

use Buckaroo\Magento2\Model\ResourceModel\SecondChance as SecondChanceResource;
use Buckaroo\Magento2\Model\SecondChance;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class SecondChanceTest extends TestCase
{
    private const TABLE = 'buckaroo_magento2_second_chance';

    public function testSaveIfStatusUnchangedOnlyWritesWhileTheStatusIsTheLoadedOne(): void
    {
        $record = $this->getMockBuilder(SecondChance::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $record->method('getId')->willReturn('7');

        $connection = $this->createMock(AdapterInterface::class);
        // The status condition covers every column, so a record changed meanwhile keeps all its data
        $connection->expects($this->once())->method('update')->with(
            self::TABLE,
            ['step' => 2, 'second_email_sent' => '2026-11-25 10:00:00', 'status' => 'completed'],
            ['entity_id = ?' => 7, 'status = ?' => 'step1_sent']
        )->willReturn(1);

        $resource = $this->getMockBuilder(SecondChanceResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable', 'getIdFieldName', '_prepareDataForSave'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(self::TABLE);
        $resource->method('getIdFieldName')->willReturn('entity_id');
        $resource->method('_prepareDataForSave')->with($record)->willReturn([
            'entity_id' => 7,
            'step' => 2,
            'second_email_sent' => '2026-11-25 10:00:00',
            'status' => 'completed',
        ]);

        $resource->saveIfStatusUnchanged($record, 'step1_sent');
    }

    public function testGetCurrentStatusReadsTheStatusFromTheDatabase(): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->expects($this->once())->method('from')->with(self::TABLE, ['status'])->willReturnSelf();
        $select->expects($this->once())->method('where')->with('entity_id = ?', 7)->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->with($select)->willReturn('clicked');

        $resource = $this->getMockBuilder(SecondChanceResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(self::TABLE);

        $this->assertSame('clicked', $resource->getCurrentStatus(7));
    }

    public function testGetCurrentStatusOfADeletedRecordIsNull(): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn(false);

        $resource = $this->getMockBuilder(SecondChanceResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(self::TABLE);

        $this->assertNull($resource->getCurrentStatus(7));
    }
}
