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
use PHPUnit\Framework\MockObject\MockObject;
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
            []
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

    public function testKeepsADisplaySettingThatAlreadyExistsForThatScope(): void
    {
        $connection = $this->createConnection(
            [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '2'],
                ['scope' => 'stores', 'scope_id' => '3', 'value' => '2'],
            ],
            ['default/0']
        );
        $connection->expects($this->once())
            ->method('insert')
            ->with('core_config_data', [
                'scope'    => 'stores',
                'scope_id' => 3,
                'path'     => 'tax/sales_display/buckaroo_fee',
                'value'    => '2',
            ]);

        $this->createPatch($connection)->apply();
    }

    public function testDoesNothingWithoutAFeeTaxClass(): void
    {
        // Without a stored tax class the fee showed excluding tax, which is the config.xml default
        $connection = $this->createConnection([], []);
        $connection->expects($this->never())->method('insert');

        $this->createPatch($connection)->apply();
    }

    /**
     * Answers the tax class lookup with $taxClassRows and the per-scope display lookup with a
     * config id when "scope/scope_id" is listed in $existingDisplayScopes. Any other query fails.
     *
     * @param array $taxClassRows
     * @param array $existingDisplayScopes
     *
     * @return AdapterInterface|MockObject
     */
    private function createConnection(array $taxClassRows, array $existingDisplayScopes): AdapterInterface
    {
        $queries = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () use (&$queries) {
            $select = $this->createMock(Select::class);
            $id = spl_object_id($select);
            $queries[$id] = ['from' => null, 'where' => []];
            $select->method('from')->willReturnCallback(function ($table, $cols) use ($select, $id, &$queries) {
                $queries[$id]['from'] = [$table, $cols];
                return $select;
            });
            $select->method('where')->willReturnCallback(
                function ($cond, $value = null) use ($select, $id, &$queries) {
                    $queries[$id]['where'][] = [$cond, $value];
                    return $select;
                }
            );
            return $select;
        });

        $connection->method('fetchAll')->willReturnCallback(function ($select) use (&$queries, $taxClassRows) {
            $this->assertSame(
                [
                    'from' => ['core_config_data', ['scope', 'scope_id', 'value']],
                    'where' => [['path = ?', 'buckaroo_magento2/account/buckaroo_fee_tax_class']],
                ],
                $queries[spl_object_id($select)]
            );
            return $taxClassRows;
        });

        // One display lookup per tax class row, for that row's own scope
        $expectedLookups = array_map(function (array $row) {
            return [$row['scope'], (int)$row['scope_id']];
        }, $taxClassRows);
        $connection->expects($this->exactly(count($taxClassRows)))
            ->method('fetchOne')
            ->willReturnCallback(function ($select) use (&$queries, &$expectedLookups, $existingDisplayScopes) {
                [$scope, $scopeId] = array_shift($expectedLookups);
                $this->assertSame(
                    [
                        'from' => ['core_config_data', ['config_id']],
                        'where' => [
                            ['path = ?', 'tax/sales_display/buckaroo_fee'],
                            ['scope = ?', $scope],
                            ['scope_id = ?', $scopeId],
                        ],
                    ],
                    $queries[spl_object_id($select)]
                );

                return in_array($scope . '/' . $scopeId, $existingDisplayScopes, true) ? '17' : false;
            });

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
