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

/**
 * @method string getActiveStatus()
 * @method string getOrderStatusSuccess()
 * @method string getOrderStatusFailed()
 * @method int    getActive()
 */
// @codingStandardsIgnoreStart
abstract class AbstractConfigProvider extends BaseAbstractConfigProvider implements CheckoutConfigProvider, ConfigProviderInterface
// @codingStandardsIgnoreEnd
{
    /**
     * This xpath should be overridden in child classes.
     */
    const XPATH_ALLOWED_CURRENCIES = '';

    /**
     * This xpath should be overridden in child classes.
     */
    const XPATH_ALLOW_SPECIFIC    = '';
    const XPATH_SPECIFIC_COUNTRY    = '';

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
            'name' => 'Van Lanschot',
            'code' => 'FVLBNL22',
            'imgName' => 'vanlanschot'
        ],
        [
            'name' => 'Revolut',
            'code' => 'REVOLT21',
            'imgName' => 'revolut'
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
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper
    ) {
        parent::__construct($scopeConfig);

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
        $issuers = array_map(
            function ($issuer) {
                if(isset($issuer['imgName'])) {
                    $issuer['img'] = $this->getImageUrl("ideal/{$issuer['imgName']}", "svg");
                }
                return $issuer;
            },
            $this->getIssuers()
        );

        return $issuers;
    }

    public function getCreditcardLogo(string $code): string
    {
        if($code === 'cartebleuevisa') {
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
     * {@inheritdoc}
     */
    public function getPaymentFee($storeId = null)
    {
        return false;
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getAllowedCurrencies($store = null)
    {
        $configuredAllowedCurrencies = trim((string)$this->getConfigFromXpath(static::XPATH_ALLOWED_CURRENCIES, $store));
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

        //if the country config is null in the store get the config value from the global('default') settings
        if (empty($configuredSpecificCountry)) {
            $configuredSpecificCountry = $this->scopeConfig->getValue(
                static::XPATH_SPECIFIC_COUNTRY
            );
        };

        if (empty($configuredSpecificCountry)) 
        {
            return [];
        }

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
        return $this->getConfigFromXpath(static::XPATH_SPECIFIC_CUSTOMER_GROUP, $store);
    }

    /**
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return mixed
     */
    public function getSpecificCustomerGroupB2B($store = null)
    {
        return $this->getConfigFromXpath(static::XPATH_SPECIFIC_CUSTOMER_GROUP_B2B, $store);
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
}
