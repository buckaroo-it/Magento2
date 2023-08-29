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

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Store\Model\Store;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Config;
use Magento\Customer\Model\Customer;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SetupModuleDataPatch implements DataPatchInterface
{
   
    /**
     * @var GiftcardCollection
     */
    protected $giftcardCollection;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var Config
     */
    private Config $eavConfig;

    /**
     * @var array
     */
    protected array $giftcardArray = [
        [
            'value' => 'boekenbon',
            'label' => 'Boekenbon'
        ],
        [
            'value' => 'boekencadeau',
            'label' => 'Boekencadeau'
        ],
        [
            'value' => 'cadeaukaartgiftcard',
            'label' => 'Cadeaukaart / Giftcard'
        ],
        [
            'value' => 'nationalekadobon',
            'label' => 'De nationale kadobon'
        ],
        [
            'value' => 'fashionucadeaukaart',
            'label' => 'Fashion Giftcard'
        ],
        [
            'value' => 'fietsbon',
            'label' => 'Fietsbon'
        ],
        [
            'value' => 'fijncadeau',
            'label' => 'Fijn Cadeau'
        ],
        [
            'value' => 'gezondheidsbon',
            'label' => 'Gezondheidsbon'
        ],
        [
            'value' => 'golfbon',
            'label' => 'Golfbon'
        ],
        [
            'value' => 'nationaletuinbon',
            'label' => 'Nationale Tuinbon'
        ],
        [
            'value' => 'vvvgiftcard',
            'label' => 'VVV Giftcard'
        ],
        [
            'value' => 'webshopgiftcard',
            'label' => 'Webshop Giftcard'
        ]
    ];

    /**
     * @var array
     */
    protected array $giftcardAdditionalArray = [
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

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param GiftcardCollection $giftcardCollection
     * @param Encryptor $encryptor
     * @param EavSetupFactory $eavSetupFactory
     * @param Config $eavConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        GiftcardCollection $giftcardCollection,
        Encryptor $encryptor,
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->giftcardCollection = $giftcardCollection;
        $this->encryptor = $encryptor;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getVersion()
    {
        return '2.0.0';
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->installOrderStatusses($this->moduleDataSetup); // 0.1.1
        $this->installBaseGiftcards($this->moduleDataSetup, $this->giftcardArray); // 1.3.0
        $this->installBaseGiftcards($this->moduleDataSetup, $this->giftcardAdditionalArray); // 1.3.0
        $this->updateFailureRedirectConfiguration($this->moduleDataSetup); // 1.5.0
        $this->updateSecretKeyConfiguration($this->moduleDataSetup); // 1.14.0
        $this->updateMerchantKeyConfiguration($this->moduleDataSetup); // 1.14.0
        $this->zeroizeGiftcardsPaymentFee($this->moduleDataSetup); // 1.25.1
        $this->giftcardPartialRefund($this->moduleDataSetup); // 1.25.2
        $this->setCustomerIDIN($this->moduleDataSetup);
        $this->setCustomerIsEighteenOrOlder($this->moduleDataSetup);
        $this->setProductIDIN($this->moduleDataSetup);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Install Order Statuses
     *
     * @param ModuleDataSetupInterface $setup
     * @return $this
     */
    protected function installOrderStatusses(ModuleDataSetupInterface $setup): SetupModuleDataPatch
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
     * Install giftcards which can be used with the Giftcards payment method
     *
     * @param ModuleDataSetupInterface $setup
     * @param array $giftcardArray
     * @return $this
     */
    protected function installBaseGiftcards(
        ModuleDataSetupInterface $setup,
        array $giftcardArray = []
    ): SetupModuleDataPatch {
        foreach ($giftcardArray as $giftcard) {
            $foundGiftcards = $this->giftcardCollection->getItemsByColumnValue('servicecode', $giftcard['value']);

            if (count($foundGiftcards) <= 0) {
                $setup->getConnection()->insert(
                    $setup->getTable('buckaroo_magento2_giftcard'),
                    [
                        'servicecode' => $giftcard['value'],
                        'label'       => $giftcard['label'],
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
     * @return $this
     */
    protected function updateFailureRedirectConfiguration(ModuleDataSetupInterface $setup): SetupModuleDataPatch
    {
        $path = 'buckaroo_magento2/account/failure_redirect';
        $data = [
            'scope'    => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'scope_id' => Store::DEFAULT_STORE_ID,
            'path'     => $path,
            'value'    => 'checkout/cart',
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        return $this;
    }

    /**
     * Empty secret key, so it will be set with correct value
     *
     * @param ModuleDataSetupInterface $setup
     * @return $this
     */
    protected function updateSecretKeyConfiguration(ModuleDataSetupInterface $setup): SetupModuleDataPatch
    {
        $path = 'buckaroo_magento2/account/secret_key';
        $data = [
            'path'  => $path,
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
     * @return $this
     */
    protected function updateMerchantKeyConfiguration(ModuleDataSetupInterface $setup): SetupModuleDataPatch
    {
        $path = 'buckaroo_magento2/account/merchant_key';
        $data = [
            'path'  => $path,
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
     * Return zero for gift cards payment fee
     *
     * @param ModuleDataSetupInterface $setup
     * @return $this
     */
    protected function zeroizeGiftcardsPaymentFee(ModuleDataSetupInterface $setup): SetupModuleDataPatch
    {
        $path = 'payment/buckaroo_magento2_giftcards/payment_fee';
        $data = [
            'path'  => $path,
            'value' => 0,
        ];

        $setup->getConnection()->update(
            $setup->getTable('core_config_data'),
            $data,
            $setup->getConnection()->quoteInto('path = ?', $path)
        );

        $path = 'payment/buckaroo_magento2_giftcards/payment_fee_label';
        $data = [
            'path'  => $path,
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
     * Add data connected with gift card partial refund
     *
     * @param ModuleDataSetupInterface $setup
     * @return $this
     */
    protected function giftcardPartialRefund(ModuleDataSetupInterface $setup): SetupModuleDataPatch
    {
        $giftcardsForPartialRefund = ['fashioncheque'];

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

    /**
     * Set customer iDIN attribute
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     * @throws LocalizedException
     */
    protected function setCustomerIDIN(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            Customer::ENTITY,
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

    /**
     * Add attribute if customer is eighteen or older iDIN
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     * @throws LocalizedException
     */
    protected function setCustomerIsEighteenOrOlder(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            Customer::ENTITY,
            'buckaroo_idin_iseighteenorolder',
            [
                'type'         => 'int',
                'label'        => 'Buckaroo iDIN IsEighteenOrOlder',
                'input'        => 'select',
                'source'       => Boolean::class,
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

    /**
     * Set product iDIN
     *
     * @param ModuleDataSetupInterface $setup
     * @return void
     */
    protected function setProductIDIN(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            Product::ENTITY,
            'buckaroo_product_idin',
            [
                'type'       => 'int',
                'label'      => 'Buckaroo iDIN',
                'input'      => 'select',
                'source'     => Boolean::class,
                'required'   => false,
                'sort_order' => 999,
                'global'     => ScopedAttributeInterface::SCOPE_STORE,
                'default'    => '0',
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
