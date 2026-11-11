<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

    /**
     * Rows returned for the order_email path and for the new path, in that order.
     *
     * @var array
     */
    private $fetchResults = [];

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchAll')->willReturnCallback(function () {
            return array_shift($this->fetchResults);
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($this->connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('core_config_data');

        $this->patch = new MigrateTransferPaymentInstructionEmail($setup);
    }

    public function testCopiesTheStoredValueOfEveryScope(): void
    {
        $this->fetchResults = [
            [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '0'],
                ['scope' => 'websites', 'scope_id' => '1', 'value' => '1'],
                ['scope' => 'stores', 'scope_id' => '2', 'value' => '0'],
            ],
            [],
        ];

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with('core_config_data', [
                ['scope' => 'default', 'scope_id' => 0, 'path' => MigrateTransferPaymentInstructionEmail::NEW_PATH, 'value' => '0'],
                ['scope' => 'websites', 'scope_id' => 1, 'path' => MigrateTransferPaymentInstructionEmail::NEW_PATH, 'value' => '1'],
                ['scope' => 'stores', 'scope_id' => 2, 'path' => MigrateTransferPaymentInstructionEmail::NEW_PATH, 'value' => '0'],
            ]);

        $this->patch->apply();
    }

    public function testKeepsAValueAlreadySetOnTheNewPath(): void
    {
        $this->fetchResults = [
            [
                ['scope' => 'default', 'scope_id' => '0', 'value' => '0'],
                ['scope' => 'stores', 'scope_id' => '2', 'value' => '0'],
            ],
            [
                ['scope' => 'default', 'scope_id' => '0'],
            ],
        ];

        $this->connection->expects($this->once())
            ->method('insertMultiple')
            ->with('core_config_data', [
                ['scope' => 'stores', 'scope_id' => 2, 'path' => MigrateTransferPaymentInstructionEmail::NEW_PATH, 'value' => '0'],
            ]);

        $this->patch->apply();
    }

    public function testDoesNothingWhenOrderEmailWasNeverSaved(): void
    {
        $this->fetchResults = [[], []];

        $this->connection->expects($this->never())->method('insertMultiple');

        $this->patch->apply();
    }

    public function testReadsTheOrderEmailPath(): void
    {
        $this->assertSame(
            'payment/buckaroo_magento2_transfer/order_email',
            MigrateTransferPaymentInstructionEmail::OLD_PATH
        );
        $this->assertSame(
            'payment/buckaroo_magento2_transfer/payment_instruction_email',
            MigrateTransferPaymentInstructionEmail::NEW_PATH
        );
    }
}
