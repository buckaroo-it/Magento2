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

use Buckaroo\Magento2\Setup\Patch\Data\MigrateBuckarooFeeDisplayType;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;

/**
 * The fee totals used to show "Incl. Tax" only for fee tax class 2. The patch writes, per scope,
 * the display type that scope showed so far, and keeps a display setting that already exists.
 */
class MigrateBuckarooFeeDisplayTypeTest extends TestCase
{
    public function testWritesTheDisplayTypeEachScopeShowedSoFar(): void
    {
        $connection = $this->createConnection(
            [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '2'],
                ['scope' => 'websites', 'scope_id' => '1', 'value' => '4'],
                ['scope' => 'stores', 'scope_id' => '3', 'value' => ''],
            ],
            [false, false, false]
        );

        $inserted = [];
        $connection->method('insert')->willReturnCallback(function ($table, array $data) use (&$inserted) {
            $inserted[] = [$table, $data['scope'], $data['scope_id'], $data['path'], $data['value']];
            return 1;
        });

        $this->createPatch($connection)->apply();

        $this->assertSame(
            [
                ['core_config_data', 'default', 0, 'tax/sales_display/buckaroo_fee', '2'],
                ['core_config_data', 'websites', 1, 'tax/sales_display/buckaroo_fee', '1'],
                ['core_config_data', 'stores', 3, 'tax/sales_display/buckaroo_fee', '1'],
            ],
            $inserted
        );
    }

    public function testKeepsADisplaySettingThatAlreadyExists(): void
    {
        $connection = $this->createConnection(
            [['scope' => 'default', 'scope_id' => '0', 'value' => '2']],
            ['17']
        );
        $connection->expects($this->never())->method('insert');

        $this->createPatch($connection)->apply();
    }

    public function testDoesNothingWithoutAFeeTaxClass(): void
    {
        // Without a stored tax class the fee showed excluding tax, which is the config.xml default
        $connection = $this->createConnection([], []);
        $connection->expects($this->never())->method('insert');

        $this->createPatch($connection)->apply();
    }

    private function createConnection(array $taxClassRows, array $existingDisplayRows): AdapterInterface
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($taxClassRows);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$existingDisplayRows);

        return $connection;
    }

    private function createPatch(AdapterInterface $connection): MigrateBuckarooFeeDisplayType
    {
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        return new MigrateBuckarooFeeDisplayType($setup);
    }
}
