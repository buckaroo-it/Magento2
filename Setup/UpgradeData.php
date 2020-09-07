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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\Store;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
    /**
     * @var \Magento\Sales\Setup\SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * @var \Magento\Quote\Setup\QuoteSetupFactory
     */
    protected $quoteSetupFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\Certificate\Collection
     */
    protected $certificateCollection;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection
     */
    protected $giftcardCollection;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

    protected $registry;

    /**
     * @var array
     */
    protected $giftcardArray = array(
        array(
            'value' => 'boekenbon',
            'label' => 'Boekenbon'
        ),
        array(
            'value' => 'boekencadeau',
            'label' => 'Boekencadeau'
        ),
        array(
            'value' => 'cadeaukaartgiftcard',
            'label' => 'Cadeaukaart / Giftcard'
        ),
        array(
            'value' => 'nationalekadobon',
            'label' => 'De nationale kadobon'
        ),
        array(
            'value' => 'fashionucadeaukaart',
            'label' => 'Fashion Giftcard'
        ),
        array(
            'value' => 'fietsbon',
            'label' => 'Fietsbon'
        ),
        array(
            'value' => 'fijncadeau',
            'label' => 'Fijn Cadeau'
        ),
        array(
            'value' => 'gezondheidsbon',
            'label' => 'Gezondheidsbon'
        ),
        array(
            'value' => 'golfbon',
            'label' => 'Golfbon'
        ),
        array(
            'value' => 'nationaletuinbon',
            'label' => 'Nationale Tuinbon'
        ),
        array(
            'value' => 'vvvgiftcard',
            'label' => 'VVV Giftcard'
        ),
        array(
            'value' => 'webshopgiftcard',
            'label' => 'Webshop Giftcard'
        )
    );

    /**
     * @var array
     */
    protected $giftcardAdditionalArray = array(
        array(
            'label' => 'Ajax Giftcard',
            'value' => 'ajaxgiftcard',
        ),
        array(
            'label' => 'Baby Giftcard',
            'value' => 'babygiftcard',
        ),
        array(
            'label' => 'Babypark Giftcard',
            'value' => 'babyparkgiftcard',
        ),
        array(
            'label' => 'Babypark Kesteren Giftcard',
            'value' => 'babyparkkesterengiftcard',
        ),
        array(
            'label' => 'Beauty Wellness',
            'value' => 'beautywellness',
        ),
        array(
            'label' => 'Boekencadeau Retail',
            'value' => 'boekencadeauretail',
        ),
        array(
            'label' => 'Boeken Voordeel',
            'value' => 'boekenvoordeel',
        ),
        array(
            'label' => 'CampingLife Giftcard',
            'value' => 'campinglifekaart',
        ),
        array(
            'label' => 'CJP betalen',
            'value' => 'cjpbetalen',
        ),
        array(
            'label' => 'Coccinelle Giftcard',
            'value' => 'coccinellegiftcard',
        ),
        array(
            'label' => 'Dan card',
            'value' => 'dancard',
        ),
        array(
            'label' => 'De Beren Cadeaukaart',
            'value' => 'deberencadeaukaart',
        ),
        array(
            'label' => 'DEEN Cadeaukaart',
            'value' => 'deencadeau',
        ),
        array(
            'label' => 'Designshops Giftcard',
            'value' => 'designshopsgiftcard',
        ),
        array(
            'label' => 'Nationale Bioscoopbon',
            'value' => 'digitalebioscoopbon',
        ),
        array(
            'label' => 'Dinner Jaarkaart',
            'value' => 'dinnerjaarkaart',
        ),
        array(
            'label' => 'D.I.O. Cadeaucard',
            'value' => 'diocadeaucard',
        ),
        array(
            'label' => 'Doe Cadeaukaart',
            'value' => 'doecadeaukaart',
        ),
        array(
            'label' => 'Doen en Co',
            'value' => 'doenenco',
        ),
        array(
            'label' => 'E-Bon',
            'value' => 'ebon',
        ),
        array(
            'label' => 'Fashion cheque',
            'value' => 'fashioncheque',
        ),
        array(
            'label' => 'Girav Giftcard',
            'value' => 'giravgiftcard',
        ),
        array(
            'label' => 'Golfbon',
            'value' => 'golfbon',
        ),
        array(
            'label' => 'Good card',
            'value' => 'goodcard',
        ),
        array(
            'label' => 'GWS',
            'value' => 'gswspeelgoedwinkel',
        ),
        array(
            'label' => 'Jewellery Giftcard',
            'value' => 'JewelleryGiftcard',
        ),
        array(
            'label' => 'Kijkshop Kado',
            'value' => 'kijkshopkado',
        ),
        array(
            'label' => 'Kijkshop Tegoed',
            'value' => 'kijkshoptegoed',
        ),
        array(
            'label' => 'Koffie Cadeau',
            'value' => 'koffiecadeau',
        ),
        array(
            'label' => 'Koken en Zo',
            'value' => 'kokenzo',
        ),
        array(
            'label' => 'Kook Cadeau',
            'value' => 'kookcadeau',
        ),
        array(
            'label' => 'Nationale Kunst & Cultuur cadeaukaart',
            'value' => 'kunstcultuurkaart',
        ),
        array(
            'label' => 'Lotto Cadeaukaart',
            'value' => 'lottocadeaukaart',
        ),
        array(
            'label' => 'Nationale Entertainment Card',
            'value' => 'nationaleentertainmentcard',
        ),
        array(
            'label' => 'Nationale Erotiekbon',
            'value' => 'nationaleerotiekbon',
        ),
        array(
            'label' => 'Nationale Juweliers Cadeaukaart',
            'value' => 'nationalejuweliers',
        ),
        array(
            'label' => 'Natures Gift',
            'value' => 'naturesgift',
        ),
        array(
            'label' => 'Natures Gift Voucher',
            'value' => 'naturesgiftvoucher',
        ),
        array(
            'label' => 'Nationale Verwen Cadeaubon',
            'value' => 'natverwencadeaubon',
        ),
        array(
            'label' => 'Nlziet',
            'value' => 'nlziet',
        ),
        array(
            'label' => 'Opladen Cadeaukaart',
            'value' => 'opladencadeau',
        ),
        array(
            'label' => 'Parfumcadeaukaart',
            'value' => 'parfumcadeaukaart',
        ),
        array(
            'label' => 'Pathé Giftcard',
            'value' => 'pathegiftcard',
        ),
        array(
            'label' => 'Pepper Cadeau',
            'value' => 'peppercadeau',
        ),
        array(
            'label' => 'Planet Crowd',
            'value' => 'planetcrowd',
        ),
        array(
            'label' => 'Podium Cadeaukaart',
            'value' => 'podiumcadeaukaart',
        ),
        array(
            'label' => 'Polare',
            'value' => 'Polare',
        ),
        array(
            'label' => 'Riem Cadeaukaart',
            'value' => 'riemercadeaukaart',
        ),
        array(
            'label' => 'Scheltema Cadeaukaart',
            'value' => 'ScheltemaCadeauKaart',
        ),
        array(
            'label' => 'Shoeclub Cadeaukaart',
            'value' => 'shoeclub',
        ),
        array(
            'label' => 'Shoes Accessories',
            'value' => 'shoesaccessories',
        ),
        array(
            'label' => 'Siebel Juweliers Cadeaukaart',
            'value' => 'siebelcadeaukaart',
        ),
        array(
            'label' => 'Siebel Juweliers Voucher',
            'value' => 'siebelvoucher',
        ),
        array(
            'label' => 'Sieraden Horloges',
            'value' => 'sieradenhorlogescadeaukaart',
        ),
        array(
            'label' => 'Simon Lévelt Cadeaukaart',
            'value' => 'simonlevelt',
        ),
        array(
            'label' => 'Sport & Fit Cadeaukaart',
            'value' => 'sportfitcadeau',
        ),
        array(
            'label' => 'Thuisbioscoop Cadeaukaart',
            'value' => 'thuisbioscoop',
        ),
        array(
            'label' => 'Tijdschriften Cadeaukaart',
            'value' => 'tijdschriftencadeau',
        ),
        array(
            'label' => 'Toto Cadeaukaart',
            'value' => 'totocadeaukaart',
        ),
        array(
            'label' => 'Van den Assem Cadeaubon',
            'value' => 'vandenassem',
        ),
        array(
            'label' => 'VDC Giftcard',
            'value' => 'vdcgiftcard',
        ),
        array(
            'label' => 'Videoland Card',
            'value' => 'videolandcard',
        ),
        array(
            'label' => 'Videoland cadeaukaart',
            'value' => 'videolandkaart',
        ),
        array(
            'label' => 'Vitaminboost cadeaukaart',
            'value' => 'vitaminboost',
        ),
        array(
            'label' => 'Vitaminstore Giftcard',
            'value' => 'vitaminstoregiftcard',
        ),
        array(
            'label' => 'Voetbalshop.nl CadeauCard',
            'value' => 'voetbalshopcadeau',
        ),
        array(
            'label' => 'Wijn Cadeau',
            'value' => 'wijncadeau',
        ),
        array(
            'label' => 'WinkelCheque',
            'value' => 'winkelcheque',
        ),
        array(
            'label' => 'Wonen en Zo',
            'value' => 'wonenzo',
        ),
        array(
            'label' => 'YinX Cadeaukaart',
            'value' => 'yinx',
        ),
        array(
            'label' => 'Yourgift Card',
            'value' => 'yourgift',
        ),
        array(
            'label' => 'YourPhotoMag',
            'value' => 'yourphotomag',
        ),
        array(
            'label' => 'Zwerfkei Cadeaukaart',
            'value' => 'zwerfkeicadeaukaart',
        ),
    );

    /**
     * @param \Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory
     * @param \Magento\Quote\Setup\QuoteSetupFactory $quoteSetupFactory
     * @param \Buckaroo\Magento2\Model\ResourceModel\Certificate\Collection $certificateCollection
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory,
        \Magento\Quote\Setup\QuoteSetupFactory $quoteSetupFactory,
        \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection $giftcardCollection,
        \Buckaroo\Magento2\Model\ResourceModel\Certificate\Collection $certificateCollection,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Framework\Registry $registry
    )
    {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->giftcardCollection = $giftcardCollection;
        $this->certificateCollection = $certificateCollection;
        $this->encryptor = $encryptor;
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($this->registry->registry('tig_buckaroo_upgrade') && !$context->getVersion()) {
            $setup->getConnection()->query(
                "SET FOREIGN_KEY_CHECKS=0"
            );
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('sales_order_status')." SET status = replace(status, 'tig_buckaroo','buckaroo_magento2') WHERE status LIKE '%tig_buckaroo%'"
            );
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('sales_order_status_state')." SET status = replace(status, 'tig_buckaroo','buckaroo_magento2') WHERE status LIKE '%tig_buckaroo%'"
            );
            $setup->getConnection()->query(
                "SET FOREIGN_KEY_CHECKS=1"
            );
            return false;
        }

        if (version_compare($context->getVersion(), '0.1.1', '<')) {
            $this->installOrderStatusses($setup);
        }

        if (version_compare($context->getVersion(), '0.1.3', '<')) {
            $this->installPaymentFeeColumns($setup);
        }

        if (version_compare($context->getVersion(), '0.1.4', '<')) {
            $this->expandPaymentFeeColumns($setup);
        }

        if (version_compare($context->getVersion(), '0.1.5', '<')) {
            $this->installInvoicePaymentFeeTaxAmountColumns($setup);
        }

        if (version_compare($context->getVersion(), '0.1.6', '<')) {
            $this->installOrderPaymentFeeTaxAmountColumns($setup);
        }

        if (version_compare($context->getVersion(), '0.9.4', '<')) {
            $this->encryptCertificates();
        }

        if (version_compare($context->getVersion(), '1.3.0', '<')) {
            $this->installBaseGiftcards($setup, $this->giftcardArray);
        }

        if (version_compare($context->getVersion(), '1.3.0', '<')) {
            $this->installBaseGiftcards($setup, $this->giftcardAdditionalArray);
        }

        if (version_compare($context->getVersion(), '1.5.0', '<')) {
            $this->updateFailureRedirectConfiguration($setup);
        }

        if (version_compare($context->getVersion(), '1.5.3', '<')) {
            $this->installPaymentFeeInclTaxColumns($setup);
        }


        if (version_compare($context->getVersion(), '1.9.2', '<')) {
            $this->installReservationNrColumn($setup);
            $this->installPushDataColumn($setup);
        }

        if (version_compare($context->getVersion(), '1.9.3', '<')) {
            $this->installIdentificationNumber($setup);
        }

        if (version_compare($context->getVersion(), '1.14.0', '<')) {
            $this->updateSecretKeyConfiguration($setup);
            $this->updateMerchantKeyConfiguration($setup);
        }

        if (version_compare($context->getVersion(), '1.18.0', '<')) {
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('sales_order_payment')." SET method = replace(method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('sales_order_grid')." SET payment_method = replace(payment_method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('sales_invoice_grid')." SET payment_method = replace(payment_method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE ".$setup->getTable('quote_payment')." SET method = replace(method, 'tig_buckaroo','buckaroo_magento2')"
            );
        }

        if (version_compare($context->getVersion(), '1.19.1', '<')) {
            $this->installAlreadyPayColumns($setup);
        }

        if (version_compare($context->getVersion(), '1.22.0', '<')) {
            $this->fixLanguageCodes($setup);
        }

        if (version_compare($context->getVersion(), '1.25.1', '<')) {
            $this->zeroizeGiftcardsPaymentFee($setup);
        }

        if (version_compare($context->getVersion(), '1.25.2', '<')) {
            $this->giftcardPartialRefund($setup);
        }

        if (version_compare($context->getVersion(), '1.25.5', '<')) {
            $this->installApprovedStatuses($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */

    public function installReservationNrColumn(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_reservation_number',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT]
        );

        return $this;
    }

    public function installIdentificationNumber(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_identification_number',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT]
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installOrderStatusses(ModuleDataSetupInterface $setup)
    {
        $select = $setup->getConnection()->select()
            ->from(
                $setup->getTable('sales_order_status'),
                [
                    'status',
                ]
            )->where(
                'status = ?',
                'buckaroo_magento2_new'
            );

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            /**
             * Add New status and state
             */
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status'),
                [
                    'status' => 'buckaroo_magento2_new',
                    'label'  => __('Buckaroo New'),
                ]
            );
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status_state'),
                [
                    'status'           => 'buckaroo_magento2_new',
                    'state'            => 'processing',
                    'is_default'       => 0,
                    'visible_on_front' => 1,
                ]
            );
        } else {
            // Do an update to turn on visible_on_front, since it already exists
            $bind = ['visible_on_front' => 1];
            $where = ['status = ?' => 'buckaroo_magento2_new'];
            $setup->getConnection()->update($setup->getTable('sales_order_status_state'), $bind, $where);
        }

        /**
         * Add Pending status and state
         */
        $select = $setup->getConnection()->select()
            ->from(
                $setup->getTable('sales_order_status'),
                [
                    'status',
                ]
            )->where(
                'status = ?',
                'buckaroo_magento2_pending_paymen'
            );

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status'),
                [
                    'status' => 'buckaroo_magento2_pending_paymen',
                    'label'  => __('Buckaroo Pending Payment'),
                ]
            );
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status_state'),
                [
                    'status'           => 'buckaroo_magento2_pending_paymen',
                    'state'            => 'processing',
                    'is_default'       => 0,
                    'visible_on_front' => 1,
                ]
            );
        } else {
            // Do an update to turn on visible_on_front, since it already exists
            $bind = ['visible_on_front' => 1];
            $where = ['status = ?' => 'buckaroo_magento2_pending_paymen'];
            $setup->getConnection()->update($setup->getTable('sales_order_status_state'), $bind, $where);
        }

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installApprovedStatuses(ModuleDataSetupInterface $setup)
    {
        $select = $setup->getConnection()->select()
            ->from(
                $setup->getTable('sales_order_status'),
                [
                    'status',
                ]
            )->where(
                'status = ?',
                'buckaroo_magento2_pending_approval'
            );

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            /**
             * Add New status and state
             */
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status'),
                [
                    'status' => 'buckaroo_magento2_pending_approval',
                    'label'  => __('Pending Approval'),
                ]
            );
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status_state'),
                [
                    'status'           => 'buckaroo_magento2_pending_approval',
                    'state'            => 'processing',
                    'is_default'       => 0,
                    'visible_on_front' => 1,
                ]
            );
        } else {
            // Do an update to turn on visible_on_front, since it already exists
            $bind = ['visible_on_front' => 1];
            $where = ['status = ?' => 'buckaroo_magento2_pending_approval'];
            $setup->getConnection()->update($setup->getTable('sales_order_status_state'), $bind, $where);
        }

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installPaymentFeeColumns(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installAlreadyPayColumns(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_already_paid',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function fixLanguageCodes(ModuleDataSetupInterface $setup)
    {
        $setup->getConnection()->query(
            "UPDATE ".$setup->getTable('core_config_data')." SET value='nl' WHERE path='payment/buckaroo_magento2_emandate/language' AND value='nl_NL'"
        );

        $setup->getConnection()->query(
            "UPDATE ".$setup->getTable('core_config_data')." SET value='en' WHERE path='payment/buckaroo_magento2_emandate/language' AND value='en_US'"
        );

        return $this;
    }

    protected function expandPaymentFeeColumns(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller = $this->quoteSetupFactory->create(['resourceName' => 'quote_setup', 'setup' => $setup]);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'base_buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_base_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote',
            'buckaroo_fee_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'base_buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_base_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quoteInstaller->addAttribute(
            'quote_address',
            'buckaroo_fee_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installInvoicePaymentFeeTaxAmountColumns(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_base_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_base_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_tax_amount',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installOrderPaymentFeeTaxAmountColumns(ModuleDataSetupInterface $setup)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_base_tax_amount_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_tax_amount_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    /**
     * Encrypt all previously saved, unencrypted certificates.
     *
     * @return $this
     */
    protected function encryptCertificates()
    {
        /**
         * @var \Buckaroo\Magento2\Model\Certificate $certificate
         */
        foreach ($this->certificateCollection as $certificate) {
            $certificate->setCertificate(
                $this->encryptor->encrypt(
                    $certificate->getCertificate()
                )
            )->setSkipEncryptionOnSave(true);

            $certificate->save();
        }

        return $this;
    }

    /**
     * Install giftcards which can be used with the Giftcards payment method
     *
     * @param ModuleDataSetupInterface $setup
     * @param array                    $giftcardArray
     *
     * @return $this
     */
    protected function installBaseGiftcards(ModuleDataSetupInterface $setup, $giftcardArray = array())
    {
        foreach ($giftcardArray as $giftcard) {
            $foundGiftcards = $this->giftcardCollection->getItemsByColumnValue('servicecode', $giftcard['value']);

            if (count($foundGiftcards) <= 0) {
                $setup->getConnection()->insert(
                    $setup->getTable('buckaroo_magento2_giftcard'),
                    [
                        'servicecode' => $giftcard['value'],
                        'label'  => $giftcard['label'],
                    ]
                );
            }
        }

        return $this;
    }

    /**
     * Update failure_redirect to the correct value
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function updateFailureRedirectConfiguration(ModuleDataSetupInterface $setup)
    {
        $path = 'buckaroo_magento2/account/failure_redirect';
        $data = [
            'scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'scope_id' => Store::DEFAULT_STORE_ID,
            'path' => $path,
            'value' => 'checkout/cart',
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        return $this;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function installPaymentFeeInclTaxColumns(ModuleDataSetupInterface $setup)
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax_invoiced',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_fee_incl_tax_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'order',
            'base_buckaroo_fee_incl_tax_refunded',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'invoice',
            'base_buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        $salesInstaller->addAttribute(
            'creditmemo',
            'base_buckaroo_fee_incl_tax',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL]
        );

        return $this;
    }

    protected function installPushDataColumn(ModuleDataSetupInterface $setup)
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        $salesInstaller->addAttribute(
            'order',
            'buckaroo_push_data',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT]
        );

        return $this;
    }


    /**
     * Empty Secret_key so it will be set with correct value
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function updateSecretKeyConfiguration(ModuleDataSetupInterface $setup)
    {
        $path = 'buckaroo_magento2/account/secret_key';
        $data = [
            'path' => $path,
            'value' => '',
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        return $this;
    }

    /**
     * Empty MerchantKey so it will be set with correct value
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function updateMerchantKeyConfiguration(ModuleDataSetupInterface $setup)
    {
        $path = 'buckaroo_magento2/account/merchant_key';
        $data = [
            'path' => $path,
            'value' => '',
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        return $this;
    }

    /**
     * zeroize giftcards payment fee
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function zeroizeGiftcardsPaymentFee(ModuleDataSetupInterface $setup)
    {
        $path = 'payment/buckaroo_magento2_giftcards/payment_fee';
        $data = [
            'path' => $path,
            'value' => 0,
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        $path = 'payment/buckaroo_magento2_giftcards/payment_fee_label';
        $data = [
            'path' => $path,
            'value' => '',
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        return $this;
    }

    /**
     * add data connected with giftcard partial refund
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return $this
     */
    protected function giftcardPartialRefund(ModuleDataSetupInterface $setup){
        $giftcardsForPartialRefund = [ 'fashioncheque' ];

        foreach ($giftcardsForPartialRefund as $giftcard) {
            $setup->getConnection()->update(
                $setup->getTable('buckaroo_magento2_giftcard'),
                [
                    'is_partial_refundable' => 1
                ],
                $setup->getConnection()->quoteInto('servicecode = ?', $giftcard)
            );
        }

        return $this;
    }
}
