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

namespace Buckaroo\Magento2\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @inheritdoc
     * @throws \Zend_Db_Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('buckaroo_magento2_giftcard')) {
            if ($installer->tableExists('tig_buckaroo_giftcard')) {
                $installer->getConnection()->renameTable(
                    $installer->getTable('tig_buckaroo_giftcard'),
                    $installer->getTable('buckaroo_magento2_giftcard')
                );
                $installer->getConnection()->query(
                    "ALTER TABLE " . $installer->getTable('buckaroo_magento2_giftcard') . " COMMENT = 'Buckaroo Giftcard'"
                );
            } else {
                $this->createGiftcardTable($installer);
            }
        }

        if (!$installer->tableExists('buckaroo_magento2_invoice')) {
            if ($installer->tableExists('tig_buckaroo_invoice')) {
                $installer->getConnection()->renameTable(
                    $installer->getTable('tig_buckaroo_invoice'),
                    $installer->getTable('buckaroo_magento2_invoice')
                );
                $installer->getConnection()->query(
                    "ALTER TABLE " . $installer->getTable('buckaroo_magento2_invoice') . " COMMENT = 'Buckaroo Invoice'"
                );
            } else {
                $this->createInvoiceTable($installer);
            }
        }

        if (!$installer->tableExists('buckaroo_magento2_group_transaction')) {
            $this->createGroupTransactionTable($installer);
        }

        if (version_compare($context->getVersion(), '1.25.2', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('buckaroo_magento2_group_transaction'),
                'refunded_amount',
                [
                    'type'     => Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment'  => 'RefundedAmount',
                ]
            );

            $installer->getConnection()->addColumn(
                $installer->getTable('buckaroo_magento2_giftcard'),
                'is_partial_refundable',
                [
                    'type'     => Table::TYPE_BOOLEAN,
                    'nullable' => true,
                    'comment'  => 'Giftcard partial refund',
                ]
            );
        }

        $this->createOptimizationIndexes($installer);

        $installer->endSetup();
    }

    /**
     * Create gift card table
     *
     * @param SchemaSetupInterface $installer
     * @throws \Zend_Db_Exception
     */
    protected function createGiftcardTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_giftcard'));

        $table->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
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
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Servicecode'
        );

        $table->addColumn(
            'label',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Label'
        );

        $table->addColumn(
            'is_partial_refundable',
            Table::TYPE_BOOLEAN,
            null,
            [
                'nullable' => false,
            ],
            'Giftcard partial refund'
        );

        $table->addColumn(
            'logo',
            Table::TYPE_TEXT,
            255,
            [
                'nullable' => true,
            ],
            'Giftcard logo'
        );

        $table->setComment('Buckaroo Giftcard');

        $installer->getConnection()->createTable($table);
    }

    /**
     * Create buckaroo invoice table
     *
     * @param SchemaSetupInterface $installer
     * @throws \Zend_Db_Exception
     */
    protected function createInvoiceTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_invoice'));

        $table->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
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
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Invoice Transaction ID'
        );

        $table->addColumn(
            'invoice_number',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Invoice Number'
        );

        $table->setComment('Buckaroo Invoice');

        $installer->getConnection()->createTable($table);
    }

    /**
     * Create group transaction table
     *
     * @param SchemaSetupInterface $installer
     * @throws \Zend_Db_Exception
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function createGroupTransactionTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_group_transaction'));

        $table->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
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
            'order_id',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'orderId'
        );

        $table->addColumn(
            'transaction_id',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Transaction Id'
        );

        $table->addColumn(
            'relatedtransaction',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Related Transaction'
        );

        $table->addColumn(
            'servicecode',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'ServiceCode'
        );

        $table->addColumn(
            'currency',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Currency'
        );

        $table->addColumn(
            'amount',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'AmountDebit'
        );

        $table->addColumn(
            'type',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'RelationType'
        );

        $table->addColumn(
            'status',
            Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Status'
        );

        $table->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Created At'
        );
        $table->setComment('Buckaroo Group Transaction');

        $installer->getConnection()->createTable($table);
    }

    /**
     * Create an index for optimization
     *
     * @param SchemaSetupInterface $installer
     */
    protected function createOptimizationIndexes(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addIndex(
            $installer->getTable('sales_payment_transaction'),
            $installer->getIdxName('sales_payment_transaction', ['txn_id']),
            ['txn_id']
        );
    }
}
