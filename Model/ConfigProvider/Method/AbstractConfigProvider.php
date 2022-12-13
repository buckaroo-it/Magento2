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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\ConfigProviderInterface as CheckoutConfigProvider;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AbstractConfigProvider as BaseAbstractConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Store\Model\ScopeInterface;

abstract class AbstractConfigProvider extends BaseAbstractConfigProvider implements
    CheckoutConfigProvider,
    ConfigProviderInterface
{
    public const XPATH_ACTIVE = 'active';
    public const XPATH_AVAILABLE_IN_BACKEND  = 'available_in_backend';
    public const XPATH_ORDER_EMAIL = 'order_email';
    public const XPATH_PAYMENT_FEE = 'payment_fee';
    public const XPATH_PAYMENT_FEE_LABEL = 'payment_fee_label';
    public const XPATH_ACTIVE_STATUS = 'active_status';
    public const XPATH_ORDER_STATUS_SUCCESS = 'order_status_success';
    public const XPATH_ORDER_STATUS_FAILED = 'order_status_failed';
    public const XPATH_LIMIT_BY_IP = 'limit_by_ip';

    public const XPATH_ALLOWED_CURRENCIES = 'allowed_currencies';
    public const XPATH_ALLOW_SPECIFIC = 'allowspecific';
    public const XPATH_SPECIFIC_COUNTRY = 'specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP = 'specificcustomergroup';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP_B2B = 'specificcustomergroupb2b';

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
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'Bunq Bank',
            'code' => 'BUNQNL2A',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
        [
            'name' => 'Van Lanschot',
            'code' => 'FVLBNL22',
        ],
        [
            'name' => 'Revolut',
            'code' => 'REVOLT21',
        ],
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
    protected function formatIssuers(): array
    {
        return array_map(
            function ($issuer) {
                $issuer['img'] = $this->getImageUrl('ico-' . $issuer['code']);
                return $issuer;
            },
            $this->getIssuers()
        );
    }

    /**
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     *
     * @return string
     */
    public function getImageUrl($imgName)
    {
        return $this->assetRepo->getUrl('Buckaroo_Magento2::images/' . $imgName . '.png');
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getAllowedCurrencies($store = null)
    {
        $configuredAllowedCurrencies = trim((string)$this->getMethodConfigValue(
            static::XPATH_ALLOWED_CURRENCIES,
            $store
        ));
        if (empty($configuredAllowedCurrencies)) {
            return $this->getBaseAllowedCurrencies();
        }

        $configuredAllowedCurrencies = explode(',', $configuredAllowedCurrencies);

        return $configuredAllowedCurrencies;
    }

    /**
     * @return array
     */
    public function getBaseAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    /**
     * @return array
     */
    public function getBaseAllowedCountries()
    {
        return $this->allowedCountries;
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getSpecificCountry($store = null)
    {
        $configuredSpecificCountry = trim((string)$this->getConfigFromXpath(static::XPATH_SPECIFIC_COUNTRY, $store));
        $configuredSpecificCountry = explode(',', $configuredSpecificCountry);

        return $configuredSpecificCountry;
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return mixed
     */
    public function getAllowSpecific($store = null)
    {
        return $this->getConfigFromXpath(static::XPATH_ALLOW_SPECIFIC, $store);
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return mixed
     */
    public function getSpecificCustomerGroup($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_SPECIFIC_CUSTOMER_GROUP, $store);
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return mixed
     */
    public function getSpecificCustomerGroupB2B($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_SPECIFIC_CUSTOMER_GROUP_B2B, $store);
    }

    /**
     * @param string|bool $method
     *
     * @return string
     */
    public function getBuckarooPaymentFeeLabel($method = false)
    {
        return $this->paymentFeeHelper->getBuckarooPaymentFeeLabel($method);
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
     * Get Active Config Valuue
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getActive($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_ACTIVE, $store);
    }

    /**
     * Get Available In Backend
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_AVAILABLE_IN_BACKEND, $store);
    }

    /**
     * Get Send order confirmation email
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getOrderEmail($store = null)
    {
        return (bool)$this->getMethodConfigValue(static::XPATH_ORDER_EMAIL, $store);
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
        $paymentFee =  $this->getMethodConfigValue(static::XPATH_PAYMENT_FEE, $store);
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
        return $this->getMethodConfigValue(static::XPATH_PAYMENT_FEE_LABEL, $store);
    }

    /**
     * Get Method specific status enabled
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getActiveStatus($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_ACTIVE_STATUS, $store);
    }

    /**
     * Get Method specific success status
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_ORDER_STATUS_SUCCESS, $store);
    }

    /**
     * Get Method specific failed status
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_ORDER_STATUS_FAILED, $store);
    }

    /**
     * Get Limit By IP
     *
     * @param null|int|string $store
     * @return mixed|null
     */
    public function getLimitByIp($store = null)
    {
        return $this->getMethodConfigValue(static::XPATH_LIMIT_BY_IP, $store);
    }
}
