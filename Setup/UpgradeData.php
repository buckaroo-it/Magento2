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

use Buckaroo\Magento2\Model\ResourceModel\Certificate\Collection;
use Magento\Eav\Model\Config;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Registry;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;
use Magento\Store\Model\Store;
use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetupFactory;
use Buckaroo\Magento2\Model\Method\PayByBank;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @codingStandardsIgnoreFile
 */

/**
 * @SuppressWarnings(PHPMD.ObsoleteUpgradeDataScript)
 */
// phpcs:ignore Magento2.Legacy.InstallUpgrade.ObsoleteUpgradeDataScript
class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
    /**
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * @var QuoteSetupFactory
     */
    protected $quoteSetupFactory;

    /**
     * @var Collection
     */
    protected $certificateCollection;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection
     */
    protected $giftcardCollection;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    protected $registry;

    /**
     * @var array
     */
    protected $giftcardArray = [
        [
            'value' => 'boekenbon',
            'label' => 'Boekenbon',
        ],
        [
            'value' => 'boekencadeau',
            'label' => 'Boekencadeau',
        ],
        [
            'value' => 'cadeaukaartgiftcard',
            'label' => 'Cadeaukaart / Giftcard',
        ],
        [
            'value' => 'nationalekadobon',
            'label' => 'De nationale kadobon',
        ],
        [
            'value' => 'fashionucadeaukaart',
            'label' => 'Fashion Giftcard',
        ],
        [
            'value' => 'fietsbon',
            'label' => 'Fietsbon',
        ],
        [
            'value' => 'fijncadeau',
            'label' => 'Fijn Cadeau',
        ],
        [
            'value' => 'gezondheidsbon',
            'label' => 'Gezondheidsbon',
        ],
        [
            'value' => 'golfbon',
            'label' => 'Golfbon',
        ],
        [
            'value' => 'nationaletuinbon',
            'label' => 'Nationale Tuinbon',
        ],
        [
            'value' => 'vvvgiftcard',
            'label' => 'VVV Giftcard',
        ],
        [
            'value' => 'webshopgiftcard',
            'label' => 'Webshop Giftcard',
        ],
    ];

    /**
     * @var array
     */
    protected $giftcardAdditionalArray = [
        [
            'label' => 'Ajax Giftcard',
            'value' => 'ajaxgiftcard',
        ],
        [
            'label' => 'Baby Giftcard',
            'value' => 'babygiftcard',
        ],
        [
            'label' => 'Babypark Giftcard',
            'value' => 'babyparkgiftcard',
        ],
        [
            'label' => 'Babypark Kesteren Giftcard',
            'value' => 'babyparkkesterengiftcard',
        ],
        [
            'label' => 'Beauty Wellness',
            'value' => 'beautywellness',
        ],
        [
            'label' => 'Boekencadeau Retail',
            'value' => 'boekencadeauretail',
        ],
        [
            'label' => 'Boeken Voordeel',
            'value' => 'boekenvoordeel',
        ],
        [
            'label' => 'CampingLife Giftcard',
            'value' => 'campinglifekaart',
        ],
        [
            'label' => 'CJP betalen',
            'value' => 'cjpbetalen',
        ],
        [
            'label' => 'Coccinelle Giftcard',
            'value' => 'coccinellegiftcard',
        ],
        [
            'label' => 'Dan card',
            'value' => 'dancard',
        ],
        [
            'label' => 'De Beren Cadeaukaart',
            'value' => 'deberencadeaukaart',
        ],
        [
            'label' => 'DEEN Cadeaukaart',
            'value' => 'deencadeau',
        ],
        [
            'label' => 'Designshops Giftcard',
            'value' => 'designshopsgiftcard',
        ],
        [
            'label' => 'Nationale Bioscoopbon',
            'value' => 'digitalebioscoopbon',
        ],
        [
            'label' => 'Dinner Jaarkaart',
            'value' => 'dinnerjaarkaart',
        ],
        [
            'label' => 'D.I.O. Cadeaucard',
            'value' => 'diocadeaucard',
        ],
        [
            'label' => 'Doe Cadeaukaart',
            'value' => 'doecadeaukaart',
        ],
        [
            'label' => 'Doen en Co',
            'value' => 'doenenco',
        ],
        [
            'label' => 'E-Bon',
            'value' => 'ebon',
        ],
        [
            'label' => 'Fashion cheque',
            'value' => 'fashioncheque',
        ],
        [
            'label' => 'Girav Giftcard',
            'value' => 'giravgiftcard',
        ],
        [
            'label' => 'Golfbon',
            'value' => 'golfbon',
        ],
        [
            'label' => 'Good card',
            'value' => 'goodcard',
        ],
        [
            'label' => 'GWS',
            'value' => 'gswspeelgoedwinkel',
        ],
        [
            'label' => 'Jewellery Giftcard',
            'value' => 'JewelleryGiftcard',
        ],
        [
            'label' => 'Kijkshop Kado',
            'value' => 'kijkshopkado',
        ],
        [
            'label' => 'Kijkshop Tegoed',
            'value' => 'kijkshoptegoed',
        ],
        [
            'label' => 'Koffie Cadeau',
            'value' => 'koffiecadeau',
        ],
        [
            'label' => 'Koken en Zo',
            'value' => 'kokenzo',
        ],
        [
            'label' => 'Kook Cadeau',
            'value' => 'kookcadeau',
        ],
        [
            'label' => 'Nationale Kunst & Cultuur cadeaukaart',
            'value' => 'kunstcultuurkaart',
        ],
        [
            'label' => 'Lotto Cadeaukaart',
            'value' => 'lottocadeaukaart',
        ],
        [
            'label' => 'Nationale Entertainment Card',
            'value' => 'nationaleentertainmentcard',
        ],
        [
            'label' => 'Nationale Erotiekbon',
            'value' => 'nationaleerotiekbon',
        ],
        [
            'label' => 'Nationale Juweliers Cadeaukaart',
            'value' => 'nationalejuweliers',
        ],
        [
            'label' => 'Natures Gift',
            'value' => 'naturesgift',
        ],
        [
            'label' => 'Natures Gift Voucher',
            'value' => 'naturesgiftvoucher',
        ],
        [
            'label' => 'Nationale Verwen Cadeaubon',
            'value' => 'natverwencadeaubon',
        ],
        [
            'label' => 'Nlziet',
            'value' => 'nlziet',
        ],
        [
            'label' => 'Opladen Cadeaukaart',
            'value' => 'opladencadeau',
        ],
        [
            'label' => 'Parfumcadeaukaart',
            'value' => 'parfumcadeaukaart',
        ],
        [
            'label' => 'Pathé Giftcard',
            'value' => 'pathegiftcard',
        ],
        [
            'label' => 'Pepper Cadeau',
            'value' => 'peppercadeau',
        ],
        [
            'label' => 'Planet Crowd',
            'value' => 'planetcrowd',
        ],
        [
            'label' => 'Podium Cadeaukaart',
            'value' => 'podiumcadeaukaart',
        ],
        [
            'label' => 'Polare',
            'value' => 'Polare',
        ],
        [
            'label' => 'Riem Cadeaukaart',
            'value' => 'riemercadeaukaart',
        ],
        [
            'label' => 'Scheltema Cadeaukaart',
            'value' => 'ScheltemaCadeauKaart',
        ],
        [
            'label' => 'Shoeclub Cadeaukaart',
            'value' => 'shoeclub',
        ],
        [
            'label' => 'Shoes Accessories',
            'value' => 'shoesaccessories',
        ],
        [
            'label' => 'Siebel Juweliers Cadeaukaart',
            'value' => 'siebelcadeaukaart',
        ],
        [
            'label' => 'Siebel Juweliers Voucher',
            'value' => 'siebelvoucher',
        ],
        [
            'label' => 'Sieraden Horloges',
            'value' => 'sieradenhorlogescadeaukaart',
        ],
        [
            'label' => 'Simon Lévelt Cadeaukaart',
            'value' => 'simonlevelt',
        ],
        [
            'label' => 'Sport & Fit Cadeaukaart',
            'value' => 'sportfitcadeau',
        ],
        [
            'label' => 'Thuisbioscoop Cadeaukaart',
            'value' => 'thuisbioscoop',
        ],
        [
            'label' => 'Tijdschriften Cadeaukaart',
            'value' => 'tijdschriftencadeau',
        ],
        [
            'label' => 'Toto Cadeaukaart',
            'value' => 'totocadeaukaart',
        ],
        [
            'label' => 'Van den Assem Cadeaubon',
            'value' => 'vandenassem',
        ],
        [
            'label' => 'VDC Giftcard',
            'value' => 'vdcgiftcard',
        ],
        [
            'label' => 'Videoland Card',
            'value' => 'videolandcard',
        ],
        [
            'label' => 'Videoland cadeaukaart',
            'value' => 'videolandkaart',
        ],
        [
            'label' => 'Vitaminboost cadeaukaart',
            'value' => 'vitaminboost',
        ],
        [
            'label' => 'Vitaminstore Giftcard',
            'value' => 'vitaminstoregiftcard',
        ],
        [
            'label' => 'Voetbalshop.nl CadeauCard',
            'value' => 'voetbalshopcadeau',
        ],
        [
            'label' => 'Wijn Cadeau',
            'value' => 'wijncadeau',
        ],
        [
            'label' => 'WinkelCheque',
            'value' => 'winkelcheque',
        ],
        [
            'label' => 'Wonen en Zo',
            'value' => 'wonenzo',
        ],
        [
            'label' => 'YinX Cadeaukaart',
            'value' => 'yinx',
        ],
        [
            'label' => 'Yourgift Card',
            'value' => 'yourgift',
        ],
        [
            'label' => 'YourPhotoMag',
            'value' => 'yourphotomag',
        ],
        [
            'label' => 'Zwerfkei Cadeaukaart',
            'value' => 'zwerfkeicadeaukaart',
        ],
    ];

    private $eavSetupFactory;

    private $eavConfig;

    /**
     * @param SalesSetupFactory                        $salesSetupFactory
     * @param QuoteSetupFactory                        $quoteSetupFactory
     * @param Collection $certificateCollection
     * @param Encryptor                       $encryptor
     * @param Registry                                   $registry
     * @param \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection    $giftcardCollection
     * @param EavSetupFactory                                               $eavSetupFactory
     * @param Config                                                        $eavConfig
     */
    public function __construct(
        SalesSetupFactory $salesSetupFactory,
        QuoteSetupFactory $quoteSetupFactory,
        \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection $giftcardCollection,
        Collection $certificateCollection,
        Encryptor $encryptor,
        Registry $registry,
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->giftcardCollection = $giftcardCollection;
        $this->certificateCollection = $certificateCollection;
        $this->encryptor = $encryptor;
        $this->registry = $registry;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig       = $eavConfig;
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
                "UPDATE "
                . $setup->getTable('sales_order_status')
                . " SET status = replace(status, 'tig_buckaroo','buckaroo_magento2') WHERE status LIKE '%tig_buckaroo%'"
            );
            $setup->getConnection()->query(
                "UPDATE "
                . $setup->getTable('sales_order_status_state')
                . " SET status = replace(status, 'tig_buckaroo','buckaroo_magento2') WHERE status LIKE '%tig_buckaroo%'"
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
                "UPDATE "
                . $setup->getTable('sales_order_payment')
                . " SET method = replace(method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE "
                . $setup->getTable('sales_order_grid')
                . " SET payment_method = replace(payment_method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE "
                . $setup->getTable('sales_invoice_grid')
                . " SET payment_method = replace(payment_method, 'tig_buckaroo','buckaroo_magento2')"
            );
            $setup->getConnection()->query(
                "UPDATE "
                . $setup->getTable('quote_payment')
                . " SET method = replace(method, 'tig_buckaroo','buckaroo_magento2')"
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

        if (version_compare($context->getVersion(), '1.46.0', '<')) {
            $this->addCustomerLastPayByBankIssuer($setup);
        }

        if (version_compare($context->getVersion(), '1.51.0', '<')) {
            // Update buckaroo_magento2_mrcash title to 'Bancontact'
            $setup->getConnection()->update(
                $setup->getTable('core_config_data'),
                ['value' => 'Bancontact'],
                ['path = ?' => 'payment/buckaroo_magento2_mrcash/title']
            );

            // Update buckaroo_magento2_billink title to 'Billink'
            $setup->getConnection()->update(
                $setup->getTable('core_config_data'),
                ['value' => 'Billink'],
                ['path = ?' => 'payment/buckaroo_magento2_billink/title']
            );
        }

        if (version_compare($context->getVersion(), '1.52.0', '<')) {
            // Update buckaroo_magento2_knaken title to 'goSettle'
            $setup->getConnection()->update(
                $setup->getTable('core_config_data'),
                ['value' => 'goSettle'],
                ['path = ?' => 'payment/buckaroo_magento2_knaken/title']
            );
        }

        $this->setCustomerIDIN($setup);

        $this->setCustomerIsEighteenOrOlder($setup);

        $this->setProductIDIN($setup);

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
            "UPDATE "
            . $setup->getTable('core_config_data')
            . " SET value='nl' WHERE path='payment/buckaroo_magento2_emandate/language' AND value='nl_NL'"
        );

        $setup->getConnection()->query(
            "UPDATE "
            . $setup->getTable('core_config_data')
            . " SET value='en' WHERE path='payment/buckaroo_magento2_emandate/language' AND value='en_US'"
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
    protected function installBaseGiftcards(ModuleDataSetupInterface $setup, $giftcardArray = [])
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
    protected function giftcardPartialRefund(ModuleDataSetupInterface $setup)
    {
        $giftcardsForPartialRefund = [ 'fashioncheque' ];

        foreach ($giftcardsForPartialRefund as $giftcard) {
            $setup->getConnection()->update(
                $setup->getTable('buckaroo_magento2_giftcard'),
                [
                    'is_partial_refundable' => 1,
                ],
                $setup->getConnection()->quoteInto('servicecode = ?', $giftcard)
            );
        }

        return $this;
    }

    protected function setCustomerIDIN(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            'buckaroo_idin',
            [
                'type'         => 'varchar',
                'label'        => 'Buckaroo iDIN',
                'input'        => 'text',
                'required'     => false,
                'visible'      => true,
                'user_defined' => false,
                'position'     => 999,
                'system'       => 0,
            ]
        );
        $buckarooIDIN = $this->eavConfig->getAttribute(Customer::ENTITY, 'buckaroo_idin');

        $buckarooIDIN->setData(
            'used_in_forms',
            ['adminhtml_customer']
        );
        $buckarooIDIN->save();
    }

    protected function addCustomerLastPayByBankIssuer(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            PayByBank::EAV_LAST_USED_ISSUER_ID,
            [
                'type'         => 'text',
                'label'        => 'Last used Buckaroo PayByBank issuer',
                'input'        => 'text',
                'default'      => '',
                'required'     => false,
                'visible'      => false,
                'user_defined' => false,
                'position'     => 999,
                'system'       => 0,
                'global'       => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
            ]
        );

        $buckarooLastPaybybank = $this->eavConfig->getAttribute(Customer::ENTITY, PayByBank::EAV_LAST_USED_ISSUER_ID);

        $buckarooLastPaybybank->save();
    }

    protected function setCustomerIsEighteenOrOlder(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Customer\Model\Customer::ENTITY,
            'buckaroo_idin_iseighteenorolder',
            [
                'type'         => 'int',
                'label'        => 'Buckaroo iDIN IsEighteenOrOlder',
                'input'        => 'select',
                'source'       => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'default'      => '0',
                'required'     => false,
                'visible'      => true,
                'user_defined' => false,
                'position'     => 999,
                'system'       => 0,
            ]
        );
        $buckarooIDIN = $this->eavConfig->getAttribute(Customer::ENTITY, 'buckaroo_idin_iseighteenorolder');

        $buckarooIDIN->setData(
            'used_in_forms',
            ['adminhtml_customer']
        );
        $buckarooIDIN->save();
    }

    protected function setProductIDIN(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'buckaroo_product_idin',
            [
                'type' => 'int',
                'label' => 'Buckaroo iDIN',
                'input' => 'select',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'required' => false,
                'sort_order' => 999,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'default' => '0',
            ]
        );
    }
}
