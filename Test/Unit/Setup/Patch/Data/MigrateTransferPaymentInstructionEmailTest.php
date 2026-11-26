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

namespace Buckaroo\Magento2\Test\Unit\Setup\Patch\Data;

use Buckaroo\Magento2\Setup\Patch\Data\MigrateTransferPaymentInstructionEmail;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MigrateTransferPaymentInstructionEmailTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var MigrateTransferPaymentInstructionEmail
     */
    private $patch;

    private const OLD_PATH = 'payment/buckaroo_magento2_transfer/order_email';
    private const NEW_PATH = 'payment/buckaroo_magento2_transfer/payment_instruction_email';

    /**
     * Rows stored in core_config_data, keyed by config path.
     *
     * @var array
     */
    private $rowsByPath = [];

    /**
     * The from() and where() calls of every select, keyed by select object id.
     *
     * @var array
     */
    private $selects = [];

    /**
     * The selects passed to fetchAll(), in execution order.
     *
     * @var array
     */
    private $executed = [];

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturnCallback(function () {
            return $this->createRecordingSelect();
        });
        $this->connection->method('fetchAll')->willReturnCallback(function ($select) {
            $query = $this->selects[spl_object_id($select)];
            $this->executed[] = $query;
            $this->assertCount(1, $query['where'], 'Each lookup filters on the path only.');
            [$condition, $path] = $query['where'][0];
            $this->assertSame('path = ?', $condition);
            $this->assertArrayHasKey($path, $this->rowsByPath, 'Unexpected config path queried: ' . $path);

            return $this->rowsByPath[$path];
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('core_config_data');

        $this->patch = new MigrateTransferPaymentInstructionEmail($setup);
    }

    /**
     * @return Select|MockObject
     */
    private function createRecordingSelect()
    {
        $select = $this->createMock(Select::class);
        $id = spl_object_id($select);
        $this->selects[$id] = ['from' => null, 'where' => []];
        $select->method('from')->willReturnCallback(function ($table, $cols) use ($select, $id) {
            $this->selects[$id]['from'] = [$table, $cols];
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select, $id) {
            $this->selects[$id]['where'][] = [$cond, $value];
            return $select;
        });

        return $select;
    }

    public function testCopiesTheStoredValueOfEveryScope(): void
    {
        $this->rowsByPath = [
            self::OLD_PATH => [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '0'],
                ['scope' => 'websites', 'scope_id' => '1', 'value' => '1'],
                ['scope' => 'stores', 'scope_id' => '2', 'value' => '0'],
            ],
            self::NEW_PATH => [],
        ];

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with('core_config_data', [
                ['scope' => 'default', 'scope_id' => 0, 'path' => self::NEW_PATH, 'value' => '0'],
                ['scope' => 'websites', 'scope_id' => 1, 'path' => self::NEW_PATH, 'value' => '1'],
                ['scope' => 'stores', 'scope_id' => 2, 'path' => self::NEW_PATH, 'value' => '0'],
            ]);

        $this->patch->apply();
    }

    public function testKeepsAValueAlreadySetOnTheNewPath(): void
    {
        $this->rowsByPath = [
            self::OLD_PATH => [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '0'],
                ['scope' => 'stores', 'scope_id' => '2', 'value' => '0'],
            ],
            self::NEW_PATH => [
                ['scope' => 'default', 'scope_id' => '0'],
            ],
        ];

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with('core_config_data', [
                ['scope' => 'stores', 'scope_id' => 2, 'path' => self::NEW_PATH, 'value' => '0'],
            ]);

        $this->patch->apply();
    }

    public function testDoesNothingWhenOrderEmailWasNeverSaved(): void
    {
        $this->rowsByPath = [self::OLD_PATH => [], self::NEW_PATH => []];

        $this->connection->expects($this->never())->method('insertMultiple');

        $this->patch->apply();
    }

    public function testReadsTheOrderEmailPathThenTheNewPath(): void
    {
        $this->rowsByPath = [self::OLD_PATH => [], self::NEW_PATH => []];

        $this->patch->apply();

        $this->assertSame(
            [
                [
                    'from' => ['core_config_data', ['scope', 'scope_id', 'value']],
                    'where' => [['path = ?', self::OLD_PATH]],
                ],
                [
                    'from' => ['core_config_data', ['scope', 'scope_id']],
                    'where' => [['path = ?', self::NEW_PATH]],
                ],
            ],
            $this->executed
        );
    }
}
