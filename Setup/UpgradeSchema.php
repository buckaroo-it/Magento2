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

class UpgradeSchema implements \Magento\Framework\Setup\UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('tig_buckaroo_giftcard')) {
            $this->createGiftcardTable($installer);
        }

        if (!$installer->tableExists('tig_buckaroo_invoice')) {
            $this->createInvoiceTable($installer);
        }

        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createGiftcardTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('tig_buckaroo_giftcard'));

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
            'servicecode',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Servicecode'
        );

        $table->addColumn(
            'label',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Label'
        );

        $table->setComment('TIG Buckaroo Giftcard');

        $installer->getConnection()->createTable($table);
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createInvoiceTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('tig_buckaroo_invoice'));

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
            'invoice_transaction_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Invoice Transaction ID'
        );

        $table->addColumn(
            'invoice_number',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Invoice Number'
        );

        $table->setComment('TIG Buckaroo Invoice');

        $installer->getConnection()->createTable($table);
    }
}
