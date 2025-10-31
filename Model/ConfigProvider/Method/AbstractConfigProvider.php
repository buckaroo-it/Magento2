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

use Magento\Store\Model\Store;
use Buckaroo\Magento2\Exception;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Checkout\Model\ConfigProviderInterface as CheckoutConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\AbstractConfigProvider as BaseAbstractConfigProvider;

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
    public const TITLE         = 'title';
    public const FINANCIAL_WARNING = 'financial_warning';


    /**
     * The asset repository to generate the correct url to our assets.
     *
     * @var Repository
     */
    protected $assetRepo;

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
     * @var LogoService
     */
    protected $logoService;


    protected $issuers = [];
    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param LogoService          $logoService
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService
    ) {
        parent::__construct($scopeConfig, static::CODE);

        $this->assetRepo = $assetRepo;
        $this->paymentFeeHelper = $paymentFeeHelper;
        $this->logoService = $logoService;

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
    public function formatIssuers()
    {
        $issuers = $this->getIssuers();
        $codeToIssuerMap = [];
        foreach ($issuers as &$issuer) {
            if (isset($issuer['imgName'])) {
                $issuer['img'] = $this->getImageUrl($issuer['imgName']);
            }
            $codeToIssuerMap[$issuer['code']] = $issuer;
        }
        if (method_exists($this, 'getSortedIssuers')) {
            $sortedCodes = $this->getSortedIssuers() ?? '';
            $sortedCodes = $sortedCodes ? explode(',', $sortedCodes) : [];
            if (!empty($sortedCodes)) {
                $sortedIssuers = [];
                foreach ($sortedCodes as $code) {
                    if (isset($codeToIssuerMap[$code])) {
                        $sortedIssuers[] = $codeToIssuerMap[$code];
                    }
                }
            }

            return $sortedIssuers ?? $issuers;

        }

        return $issuers;
    }

    public function getCreditcardLogo(string $code): string
    {
        return $this->logoService->getCreditcard($code);
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
     * @param string          $field
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
     *
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
     *
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
     *
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
     *
     * @return mixed
     */
    public function getSpecificCustomerGroupB2B($store = null)
    {
        return $this->getMethodConfigValue(static::SPECIFIC_CUSTOMER_GROUP_B2B, $store);
    }

    /**
     * Get buckaroo payment fee
     *
     * @return string|null
     */
    public function getBuckarooPaymentFeeLabel()
    {
        return $this->paymentFeeHelper->getBuckarooPaymentFeeLabel();
    }

    /**
     * Get buckaroo payment method title (excluding fee)
     *
     * @param null|mixed $store
     *
     * @return string|null
     */
    public function getTitle($store = null): ?string
    {
        return $this->getMethodConfigValue(static::TITLE, $store);
    }
    /**
     * Get Active Config Value
     *
     * @param null|int|string $store
     *
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
     *
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
     *
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
        return false;
    }

    /**
     * Get Payment fee frontend label
     *
     * @param null|int|string $store
     *
     * @return mixed|null
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->getMethodConfigValue(static::PAYMENT_FEE_LABEL, $store);
    }

    /**
     * Get Account Payment fee frontend label
     *
     * @param null|int|string $store
     *
     * @return mixed|null
     */
    protected function getAccoutPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            Account::XPATH_ACCOUNT_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Method specific status enabled
     *
     * @param null|int|string $store
     *
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
     *
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
     *
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
     *
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
     *
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
     *
     * @return mixed
     */
    public function getSubtextColor($store = null)
    {
        return $this->getMethodConfigValue(static::SUBTEXT_COLOR, $store);
    }

    /**
     * Can Show Financial Warning
     *
     * @param null|mixed $store
     *
     * @return bool
     */
    public function canShowFinancialWarning($store = null): bool
    {
        return $this->getMethodConfigValue(static::FINANCIAL_WARNING, $store) !== "0";
    }

    /**
     * Is test mode
     *
     * @param null|mixed $store
     *
     * @return bool
     */
    protected function isTestMode($store = null): bool
    {
        return $this->getActive($store) == "1";
    }

    /**
     * Get all issuers not sorted
     *
     * @return array
     */
    public function getAllIssuers(): array
    {
        $issuers = $this->getIssuers();
        $issuersPrepared = [];
        foreach ($issuers as $issuer) {
            $issuer['img'] = $this->getImageUrl($issuer['imgName']);
            $issuersPrepared[$issuer['code']] = $issuer;
        }

        return $issuersPrepared;
    }

    public function getConfig(): array
    {
        return $this->fullConfig();
    }

    protected function fullConfig(array $additonal = []): array
    {

        if (!$this->getActive()) {
            return [];
        }

        return [
            'payment' => [
                'buckaroo' => [
                    static::CODE => array_merge_recursive(
                        [
                            'paymentFeeLabel'   => $this->getBuckarooPaymentFeeLabel(),
                            'title'             => $this->getTitle(),
                            'subtext'           => $this->getSubtext(),
                            'subtext_style'     => $this->getSubtextStyle(),
                            'subtext_color'     => $this->getSubtextColor(),
                            'allowedCurrencies' => $this->getAllowedCurrencies(),
                            'isTestMode'        => $this->isTestMode(),
                            'logo'              => $this->getLogo(),
                        ],
                        $additonal
                    )
                ],
            ]
        ];
    }

    public function getLogo():string
    {
        return $this->logoService->getPayment(str_replace("buckaroo_magento2_", "", static::CODE));
    }
}
