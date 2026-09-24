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

use Buckaroo\Magento2\Setup\Patch\Data\UpdateBuckarooAccountConfig;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;

/**
 * In 1.x `buckaroo_magento2/account/active` held Off/Test/Live (0/1/2) and was only read as
 * on/off; in 2.x it is a Yes/No switch. The patch must translate the legacy Live value (2)
 * and leave every other scope value, including Off, exactly as the merchant set it.
 */
class UpdateBuckarooAccountConfigTest extends TestCase
{
    public function testRewritesOnlyTheLegacyLiveValue(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with(
                'core_config_data',
                ['value' => '1'],
                ['path = ?' => 'buckaroo_magento2/account/active', 'value = ?' => '2']
            )
            ->willReturn(1);
        $connection->expects($this->never())->method('insert');

        $this->createPatch($connection)->apply();
    }

    public function testDoesNotInsertADefaultWhenNoValueIsStored(): void
    {
        // A fresh install has no row; etc/config.xml supplies the default, not the patch.
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('update')->willReturn(0);
        $connection->expects($this->never())->method('insert');
        $connection->expects($this->never())->method('insertOnDuplicate');

        $this->createPatch($connection)->apply();
    }

    private function createPatch(AdapterInterface $connection): UpdateBuckarooAccountConfig
    {
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        return new UpdateBuckarooAccountConfig($setup);
    }
}
