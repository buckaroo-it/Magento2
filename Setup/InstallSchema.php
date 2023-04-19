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
use Magento\Framework\Registry;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;
use Zend_Db_Exception;
use Zend_Db_Expr;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @var SalesSetupFactory
     */
    protected SalesSetupFactory $salesSetupFactory;

    /**
     * @var QuoteSetupFactory
     */
    protected QuoteSetupFactory $quoteSetupFactory;

    /**
     * @param SalesSetupFactory $salesSetupFactory
     * @param QuoteSetupFactory $quoteSetupFactory
     * @param Registry $registry
     */
    public function __construct(
        SalesSetupFactory $salesSetupFactory,
        QuoteSetupFactory $quoteSetupFactory,
        Registry $registry
    ) {
        $this->registry = $registry;
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
    }

    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $installer = $setup;
        $installer->startSetup();

        $this->createBuckarooCertificateTable($installer);
        $this->createGroupTransactionTable($installer);

        $this->installOrderPaymentFeeTaxAmountColumns($installer);
        $this->installPaymentFeeColumns($installer);
        $this->expandPaymentFeeColumns($installer);
        $this->installInvoicePaymentFeeTaxAmountColumns($installer);
        $this->installPaymentFeeInclTaxColumns($installer);
        $this->installReservationNrColumn($installer);
        $this->installPushDataColumn($installer);
        $this->installIdentificationNumber($installer);
        $this->installReservationNrColumn($installer);
        $this->installAlreadyPayColumns($installer);

        $installer->endSetup();
    }

    /**
     * Create Buckaroo Certificate Table
     *
     * @param SchemaSetupInterface $installer
     * @return void
     * @throws Zend_Db_Exception
     */
    private function createBuckarooCertificateTable(SchemaSetupInterface $installer): void
    {
        if (!$installer->tableExists('buckaroo_magento2_certificate')) {
            if ($installer->tableExists('tig_buckaroo_certificate')) {
                $this->registry->register('tig_buckaroo_upgrade', 1);
                $installer->getConnection()->renameTable(
                    $installer->getTable('tig_buckaroo_certificate'),
                    $installer->getTable('buckaroo_magento2_certificate')
                );
                $installer->getConnection()->changeTableComment(
                    $installer->getTable('buckaroo_magento2_certificate'),
                    'Buckaroo Certificate'
                );

                $updateData = [
                    ['table' => 'core_config_data', 'field' => 'path'],
                    ['table' => 'sales_order_payment', 'field' => 'method'],
                    ['table' => 'sales_order_grid', 'field' => 'payment_method'],
                    ['table' => 'sales_invoice_grid', 'field' => 'payment_method'],
                    ['table' => 'quote_payment', 'field' => 'method']
                ];

                foreach ($updateData as $data) {
                    $tableName = $installer->getTable($data['table']);
                    $fieldName = $data['field'];

                    $installer->getConnection()->update(
                        $tableName,
                        [$fieldName => new Zend_Db_Expr("REPLACE($fieldName, 'tig_buckaroo', 'buckaroo_magento2')")],
                        [$fieldName . ' LIKE ?' => '%tig_buckaroo%']
                    );
                }
            } else {
                $table = $installer->getConnection()
                    ->newTable($installer->getTable('buckaroo_magento2_certificate'));
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
                    'certificate',
                    Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => false,
                    ],
                    'Certificate'
                );

                $table->addColumn(
                    'name',
                    Table::TYPE_TEXT,
                    255,
                    [
                        'nullable' => false,
                    ],
                    'Name'
                );

                $table->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    [],
                    'Created At'
                );

                $table->setComment('Buckaroo Certificate');

                $installer->getConnection()->createTable($table);
            }
        }
    }

    /**
     * Create group transaction table
     *
     * @param SchemaSetupInterface $installer
     * @return void
     * @throws Zend_Db_Exception
     */
    private function createGroupTransactionTable(SchemaSetupInterface $installer): void
    {
        if (!$installer->tableExists('buckaroo_magento2_group_transaction')) {
            $table = $installer->getConnection()
                ->newTable($installer->getTable('buckaroo_magento2_group_transaction'));
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
    }

    /**
     * Add more buckaroo fee columns on order
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function installOrderPaymentFeeTaxAmountColumns(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );
    }

    /**
     * Add buckaroo fee columns on quote, quote_address, order, invoice, credit memo
     *
     * @param SchemaSetupInterface $setup
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function installPaymentFeeColumns(SchemaSetupInterface $setup): void
    {
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $setup->startSetup();

        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee',
            ['type' => Table::TYPE_DECIMAL]
        );
    }

    /**
     * Add more buckaroo fee columns on quote, quote_address, order
     *
     * @param SchemaSetupInterface $setup
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function expandPaymentFeeColumns(SchemaSetupInterface $setup): void
    {
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_base_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_base_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );
    }

    /**
     * Add more buckaroo fee columns on invoice, credit memo
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function installInvoicePaymentFeeTaxAmountColumns(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_base_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_base_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_tax_amount',
            ['type' => Table::TYPE_DECIMAL]
        );
    }

    /**
     * Add more buckaroo fee columns on order, invoice and credit memo
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function installPaymentFeeInclTaxColumns(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax_invoiced',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax_refunded',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_fee_incl_tax',
            ['type' => Table::TYPE_DECIMAL]
        );
    }

    /**
     * Add reservation number column on order
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function installReservationNrColumn(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_reservation_number',
            ['type' => Table::TYPE_TEXT]
        );
    }

    /**
     * Add push data column on order
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function installPushDataColumn(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_push_data',
            ['type' => Table::TYPE_TEXT]
        );
    }

    /**
     * Add identification number on order
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    public function installIdentificationNumber(SchemaSetupInterface $setup): void
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_identification_number',
            ['type' => Table::TYPE_TEXT]
        );
    }

    /**
     * Add already pay columns on quote, quote_address, order, invoice, credit memo
     *
     * @param SchemaSetupInterface $setup
     * @return void
     */
    protected function installAlreadyPayColumns(SchemaSetupInterface $setup): void
    {
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_already_paid',
            ['type' => Table::TYPE_DECIMAL]
        );
    }
}
