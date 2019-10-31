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
     * @var \TIG\Buckaroo\Model\ResourceModel\Certificate\Collection
     */
    protected $certificateCollection;

    /**
     * @var \TIG\Buckaroo\Model\ResourceModel\Giftcard\Collection
     */
    protected $giftcardCollection;

    /**
     * @var \Magento\Framework\Encryption\Encryptor
     */
    protected $encryptor;

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
     * @param \TIG\Buckaroo\Model\ResourceModel\Certificate\Collection $certificateCollection
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     */
    public function __construct(
        \Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory,
        \Magento\Quote\Setup\QuoteSetupFactory $quoteSetupFactory,
        \TIG\Buckaroo\Model\ResourceModel\Giftcard\Collection $giftcardCollection,
        \TIG\Buckaroo\Model\ResourceModel\Certificate\Collection $certificateCollection,
        \Magento\Framework\Encryption\Encryptor $encryptor
    )
    {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->giftcardCollection = $giftcardCollection;
        $this->certificateCollection = $certificateCollection;
        $this->encryptor = $encryptor;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

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
                'tig_buckaroo_new'
            );

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            /**
             * Add New status and state
             */
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status'),
                [
                    'status' => 'tig_buckaroo_new',
                    'label'  => __('TIG Buckaroo New'),
                ]
            );
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status_state'),
                [
                    'status'           => 'tig_buckaroo_new',
                    'state'            => 'processing',
                    'is_default'       => 0,
                    'visible_on_front' => 1,
                ]
            );
        } else {
            // Do an update to turn on visible_on_front, since it already exists
            $bind = ['visible_on_front' => 1];
            $where = ['status = ?' => 'tig_buckaroo_new'];
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
                'tig_buckaroo_pending_payment'
            );

        if (count($setup->getConnection()->fetchAll($select)) == 0) {
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status'),
                [
                    'status' => 'tig_buckaroo_pending_payment',
                    'label'  => __('TIG Buckaroo Pending Payment'),
                ]
            );
            $setup->getConnection()->insert(
                $setup->getTable('sales_order_status_state'),
                [
                    'status'           => 'tig_buckaroo_pending_payment',
                    'state'            => 'processing',
                    'is_default'       => 0,
                    'visible_on_front' => 1,
                ]
            );
        } else {
            // Do an update to turn on visible_on_front, since it already exists
            $bind = ['visible_on_front' => 1];
            $where = ['status = ?' => 'tig_buckaroo_pending_payment'];
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
         * @var \TIG\Buckaroo\Model\Certificate $certificate
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
                    $setup->getTable('tig_buckaroo_giftcard'),
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
        $path = 'tig_buckaroo/account/failure_redirect';
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
}
