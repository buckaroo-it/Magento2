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

namespace TIG\Buckaroo\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('tig_buckaroo_certificate')) {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('tig_buckaroo_certificate'));
            $table->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ],
                'Entity ID'
            );

            $table->addColumn(
                'certificate',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                [
                    'nullable' => false,
                ],
                'Certificate'
            );

            $table->addColumn(
                'name',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false,
                ],
                'Name'
            );

            $table->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                [],
                'Created At'
            );

            $table->setComment('TIG Buckaroo Certificate');

            $installer->getConnection()->createTable($table);
        }
    }
}
