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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AbstractConfigProvider as BaseAbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Checkout\Model\ConfigProviderInterface as CheckoutConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractConfigProvider extends BaseAbstractConfigProvider implements
    CheckoutConfigProvider,
    ConfigProviderInterface
{
    public const CODE = 'buckaroo_magento2_';

    public const ACTIVE               = 'active';
    public const AVAILABLE_IN_BACKEND = 'available_in_backend';
    public const ORDER_EMAIL          = 'order_email';
    public const PAYMENT_FEE          = 'payment_fee';
    public const PAYMENT_FEE_LABEL    = 'payment_fee_label';
    public const ACTIVE_STATUS        = 'active_status';
    public const ORDER_STATUS_SUCCESS = 'order_status_success';
    public const ORDER_STATUS_FAILED  = 'order_status_failed';

    public const ALLOWED_CURRENCIES          = 'allowed_currencies';
    public const ALLOW_SPECIFIC              = 'allowspecific';
    public const SPECIFIC_COUNTRY            = 'specificcountry';
    public const SPECIFIC_CUSTOMER_GROUP     = 'specificcustomergroup';
    public const SPECIFIC_CUSTOMER_GROUP_B2B = 'specificcustomergroupb2b';

    public const SUBTEXT       = 'subtext';
    public const SUBTEXT_STYLE = 'subtext_style';
    public const SUBTEXT_COLOR = 'subtext_color';

    public const FINANCIAL_WARNING = 'financial_warning';


    /**
     * The asset repository to generate the correct url to our assets.
     *
     * @var Repository
     */
    protected $assetRepo;

    /**
     * The list of issuers. This is filled by the child classes.
     *
     * @var array
     */
    protected array $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
            'imgName' => 'abnamro'
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
            'imgName' => 'asnbank'
        ],
        [
            'name' => 'Bunq Bank',
            'code' => 'BUNQNL2A',
            'imgName' => 'bunq'
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
            'imgName' => 'ing'
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
            'imgName' => 'knab'
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
            'imgName' => 'rabobank'
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
            'imgName' => 'regiobank'
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
            'imgName' => 'sns'
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
            'imgName' => 'triodos'
        ],
        [
            'name' => 'Van Lanschot Kempen',
            'code' => 'FVLBNL22',
            'imgName' => 'vanlanschot'
        ],
        [
            'name' => 'Revolut',
            'code' => 'REVOLT21',
            'imgName' => 'revolut'
        ],
        [
            'name' => 'Yoursafe',
            'code' => 'BITSNL2A',
            'imgName' => 'yoursafe'
        ],
        [
            'name' => 'N26',
            'code' => 'NTSBDEB1',
            'imgName' => 'n26'
        ],
        [
            'name' => 'Nationale Nederlanden',
            'code' => 'NNBANL2G',
            'imgName' => 'nn'
        ]
    ];

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var array|null
     */
    protected $allowedCurrencies = null;

    /**
     * @var array|null
     */
    protected $allowedCountries = null;

    /**
     * @var PaymentFee
     */
    protected $paymentFeeHelper;

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper
    ) {
        parent::__construct($scopeConfig, static::CODE);

        $this->assetRepo = $assetRepo;
        $this->paymentFeeHelper = $paymentFeeHelper;

        if (!$this->allowedCurrencies) {
            $this->allowedCurrencies = $allowedCurrencies->getAllowedCurrencies();
        }
    }

    /**
     * Retrieve the list of issuers.
     *
     * @return array
     */
    public function getIssuers()
    {
        return $this->issuers;
    }

    /**
     * Format the issuers list so the img index is filled with the correct url.
     *
     * @return array
     */
    protected function formatIssuers()
    {
        return array_map(
            function ($issuer) {
                if (isset($issuer['imgName'])) {
                    $issuer['img'] = $this->getImageUrl("ideal/{$issuer['imgName']}", "svg");
                }
                return $issuer;
            },
            $this->getIssuers()
        );
    }

    public function getCreditcardLogo(string $code): string
    {
        if ($code === 'cartebleuevisa') {
            $code = 'cartebleue';
        }

        return $this->getImageUrl("creditcards/{$code}", "svg");
    }

    /**
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     * @param string $extension
     *
     * @return string
     */
    public function getImageUrl($imgName, string $extension = 'png')
    {
        return $this->assetRepo->getUrl("Buckaroo_Magento2::images/{$imgName}.{$extension}");
    }

    /**
     * Get Allowed Currencies for specific payment method or get defaults
     *
     * @param null|int|Store $store
     *
     * @return array
     */
    public function getAllowedCurrencies($store = null)
    {
        $configuredAllowedCurrencies = trim((string)$this->getMethodConfigValue(
            static::ALLOWED_CURRENCIES,
            $store
        ));
        if (empty($configuredAllowedCurrencies)) {
            return $this->getBaseAllowedCurrencies();
        }

        return explode(',', $configuredAllowedCurrencies);
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param null|int|string $storeId
     *
     * @return mixed
     */
    public function getMethodConfigValue(string $field, $storeId = null)
    {
        if (static::CODE === null || $this->pathPattern === null) {
            return null;
        }

        return $this->scopeConfig->getValue(
            sprintf($this->pathPattern, static::CODE, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Base Allowed Currencies
     *
     * @return array
     */
    public function getBaseAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    /**
     * Returns an array of base allowed countries.
     *
     * @return array
     */
    public function getBaseAllowedCountries()
    {
        return $this->allowedCountries;
    }

    /**
     * Returns an array of specific countries configured for the specified store.
     *
     * @param null|int|Store $store
     * @return array
     */
    public function getSpecificCountry($store = null)
    {
        $configuredSpecificCountry = trim((string)$this->getConfigFromXpath(static::SPECIFIC_COUNTRY, $store));

        //if the country config is null in the store get the config value from the global('default') settings
        if (empty($configuredSpecificCountry)) {
            $configuredSpecificCountry = $this->scopeConfig->getValue(
                static::SPECIFIC_COUNTRY
            );
        }

        if (empty($configuredSpecificCountry)) {
            return [];
        }

        return explode(',', $configuredSpecificCountry);
    }

    /**
     * Is payment methods used only from applicable countries
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getAllowSpecific($store = null)
    {
        return $this->getConfigFromXpath(static::ALLOW_SPECIFIC, $store);
    }

    /**
     * Allow customer groups
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getSpecificCustomerGroup($store = null)
    {
        return $this->getMethodConfigValue(static::SPECIFIC_CUSTOMER_GROUP, $store);
    }

    /**
     * Allow customer groups for B2B clients
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getSpecificCustomerGroupB2B($store = null)
    {
        return $this->getMethodConfigValue(static::SPECIFIC_CUSTOMER_GROUP_B2B, $store);
    }

    /**
     * Get buckaroo payment fee
     *
     * @param string|bool $method
     * @return string
     * @throws Exception
     */
    public function getBuckarooPaymentFeeLabel($method = false)
    {
        return $this->paymentFeeHelper->getBuckarooPaymentFeeLabel($method);
    }

    /**
     * Get Active Config Value
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getActive($store = null)
    {
        return $this->getMethodConfigValue(static::ACTIVE, $store);
    }

    /**
     * Get Available In Backend
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->getMethodConfigValue(static::AVAILABLE_IN_BACKEND, $store);
    }

    /**
     * Get Send order confirmation email
     *
     * @param null|int|string $store
     * @return bool
     */
    public function hasOrderEmail($store = null): bool
    {
        return (bool)$this->getMethodConfigValue(static::ORDER_EMAIL, $store);
    }

    /**
     * Get Payment fee Float Value
     *
     * @param null|int|string $store
     *
     * @return float|false
     */
    public function getPaymentFee($store = null)
    {
        $paymentFee = $this->getMethodConfigValue(static::PAYMENT_FEE, $store);
        return $paymentFee ? (float)$paymentFee : false;
    }

    /**
     * Get Payment fee frontend label
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->getMethodConfigValue(static::PAYMENT_FEE_LABEL, $store);
    }

    /**
     * Get Method specific status enabled
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getActiveStatus($store = null)
    {
        return $this->getMethodConfigValue(static::ACTIVE_STATUS, $store);
    }

    /**
     * Get Method specific success status
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->getMethodConfigValue(static::ORDER_STATUS_SUCCESS, $store);
    }

    /**
     * Get Method specific failed status
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->getMethodConfigValue(static::ORDER_STATUS_FAILED, $store);
    }

    /**
     * Get subtext
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getSubtext($store = null)
    {
        return $this->getMethodConfigValue(static::SUBTEXT, $store);
    }

    /**
     * Get subtext style
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getSubtextStyle($store = null)
    {
        return $this->getMethodConfigValue(static::SUBTEXT_STYLE, $store);
    }

    /**
     * Get subtext color
     *
     * @param null|int|Store $store
     * @return mixed
     */
    public function getSubtextColor($store = null)
    {
        return $this->getMethodConfigValue(static::SUBTEXT_COLOR, $store);
    }

    /**
     * Can Show Financial Warning
     *
     * @return boolean
     */
    protected function canShowFinancialWarning($store = null): bool
    {
        return $this->getMethodConfigValue(static::FINANCIAL_WARNING, $store) !== "0";
    }

    /**
     * Is test mode
     *
     * @return boolean
     */
    protected function isTestMode($store = null): bool
    {
        return $this->getActive($store) == "1";
    }
}
