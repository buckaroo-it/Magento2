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

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Buckaroo\Magento2\Service\Push\ReceivedTransactionStatusRestorer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReceivedTransactionStatusRestorerTest extends TestCase
{
    private const KEY = DefaultProcessor::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES;

    public static function restoreProvider(): array
    {
        return [
            'new entry is removed' => [
                ['had_previous' => false, 'previous' => null],
                ['OTHER' => '190'],
            ],
            'replaced entry gets its old value back' => [
                ['had_previous' => true, 'previous' => '791'],
                ['OTHER' => '190', 'KEY1' => '791'],
            ],
        ];
    }

    #[DataProvider('restoreProvider')]
    public function testOnlyTheRecordedEntryChanges(array $recorded, array $expectedStatuses): void
    {
        $stored = ['method_title' => 'iDEAL', self::KEY => ['OTHER' => '190', 'KEY1' => '190']];
        $written = null;

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn(json_encode($stored));
        $connection->expects($this->once())->method('update')
            ->with('sales_order_payment', $this->anything(), ['entity_id = ?' => 7])
            ->willReturnCallback(function ($table, array $data) use (&$written) {
                $this->assertSame('sales_order_payment', $table);
                $written = json_decode($data['additional_information'], true);
                return 1;
            });
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        (new ReceivedTransactionStatusRestorer($resource, new Json()))
            ->restore(['payment_id' => 7, 'transaction_key' => 'KEY1'] + $recorded);

        $this->assertSame($expectedStatuses, $written[self::KEY]);
        $this->assertSame('iDEAL', $written['method_title']);
    }

    public function testNothingIsWrittenWithoutAPaymentOrKey(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        (new ReceivedTransactionStatusRestorer($resource, new Json()))->restore(['payment_id' => 0, 'transaction_key' => '']);
    }
}
