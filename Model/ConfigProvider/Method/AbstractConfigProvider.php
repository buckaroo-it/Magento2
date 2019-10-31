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
namespace TIG\Buckaroo\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\ConfigProviderInterface as CheckoutConfigProvider;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\ConfigProvider\AbstractConfigProvider as BaseAbstractConfigProvider;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies;

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
    protected $issuers = [];

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
                $issuer['img'] = $this->getImageUrl('ico-' . $issuer['code']);

                return $issuer;
            },
            $this->getIssuers()
        );

        return $issuers;
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
        return $this->assetRepo->getUrl('TIG_Buckaroo::images/' . $imgName . '.png');
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
        $configuredAllowedCurrencies = trim($this->getConfigFromXpath(static::XPATH_ALLOWED_CURRENCIES, $store));
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
        $configuredSpecificCountry = trim($this->getConfigFromXpath(static::XPATH_SPECIFIC_COUNTRY, $store));
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
     * @param string|bool $method
     *
     * @return string
     */
    public function getBuckarooPaymentFeeLabel($method = false)
    {
        return $this->paymentFeeHelper->getBuckarooPaymentFeeLabel($method);
    }
}
