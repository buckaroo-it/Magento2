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

    public function testSaveIfStatusUnchangedOnlyMovesTheStatusFromTheLoadedOne(): void
    {
        $record = $this->getMockBuilder(SecondChance::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStatus'])
            ->getMock();
        $record->method('getId')->willReturn('7');
        $record->method('getStatus')->willReturn('completed');

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')->with('status = ?', 'step1_sent')->willReturn("status = 'step1_sent'");
        $connection->method('quote')->with('completed')->willReturn("'completed'");
        $connection->expects($this->once())->method('getCheckSql')
            ->with("status = 'step1_sent'", "'completed'", 'status')
            ->willReturn('CHECK');
        $connection->expects($this->once())->method('update')->with(
            self::TABLE,
            ['step' => 2, 'second_email_sent' => '2026-11-25 10:00:00', 'status' => 'CHECK'],
            ['entity_id = ?' => 7]
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
}
