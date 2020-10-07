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

namespace Buckaroo\Magento2\Setup;

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

        if (!$installer->tableExists('buckaroo_magento2_giftcard')) {
            if ($installer->tableExists('tig_buckaroo_giftcard')) {
                $installer->getConnection()->renameTable(
                    $installer->getTable('tig_buckaroo_giftcard'),
                    $installer->getTable('buckaroo_magento2_giftcard')
                );
                $installer->getConnection()->query(
                    "ALTER TABLE ".$installer->getTable('buckaroo_magento2_giftcard')." COMMENT = 'Buckaroo Giftcard'"
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
                    "ALTER TABLE ".$installer->getTable('buckaroo_magento2_invoice')." COMMENT = 'Buckaroo Invoice'"
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
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'RefundedAmount'
                ]
            );

            $installer->getConnection()->addColumn(
                $installer->getTable('buckaroo_magento2_giftcard'),
                'is_partial_refundable',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                    'nullable' => true,
                    'comment' => 'Giftcard partial refund'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.26.1', '<')) {
            $this->addAWaitForRefundApprovalOrderItemColumn($installer);
            if (!$installer->tableExists('buckaroo_magento2_waiting_for_approval')) {
                $this->createWaitingForApprovalTable($installer);
            }

        }

        $installer->endSetup();
    }

    protected function addAWaitForRefundApprovalOrderItemColumn(SchemaSetupInterface $installer)
    {
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_item'),
            'buckaroo_wait_for_approval_refund_item',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'nullable' => true,
                'comment' => 'Order item count waiting for refund approve'
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createGiftcardTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_giftcard'));

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

        $table->setComment('Buckaroo Giftcard');

        $installer->getConnection()->createTable($table);
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createInvoiceTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_invoice'));

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

        $table->setComment('Buckaroo Invoice');

        $installer->getConnection()->createTable($table);
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createGroupTransactionTable(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_group_transaction'));

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
            'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'orderId'
        );

        $table->addColumn(
            'transaction_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Transaction Id'
        );

        $table->addColumn(
            'relatedtransaction',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Related Transaction'
        );

        $table->addColumn(
            'servicecode',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'ServiceCode'
        );

        $table->addColumn(
            'currency',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Currency'
        );

        $table->addColumn(
            'amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'AmountDebit'
        );

        $table->addColumn(
            'type',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'RelationType'
        );

        $table->addColumn(
            'status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Status'
        );

        $table->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            [],
            'Created At'
        );
        $table->setComment('Buckaroo Group Transaction');

        $installer->getConnection()->createTable($table);
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    protected function createWaitingForApprovalTable($installer)
    {
        $table = $installer->getConnection()->newTable($installer->getTable('buckaroo_magento2_waiting_for_approval'));

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
          'order_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Order ID'
        );

        $table->addColumn(
            'transaction_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Transaction Id'
        );

        $table->addColumn(
            'transaction_key',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            null,
            [
                'nullable' => false,
            ],
            'Transaction Original Key'
        );

        $table->addColumn(
            'buckaroo_shipping_count',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            null,
            [
                'nullable' => true,
            ],
            'Waiting for approval shipping price'
        );

        $table->addColumn(
            'buckaroo_is_fee_waiting_for_refund',
            \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
            null,
            [
                'nullable' => true,
            ],
            'Is Buckaroo fee waiting for refund'
        );

        $table->setComment('Buckaroo Waiting For Approval');

        $installer->getConnection()->createTable($table);
    }
}
