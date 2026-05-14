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

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class Googlepay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_googlepay';

    public const XPATH_GOOGLEPAY_ACTIVE                            = 'active';
    public const XPATH_GOOGLEPAY_AVAILABLE_BUTTONS                 = 'available_buttons';
    public const XPATH_GOOGLEPAY_BUTTON_STYLE                      = 'button_style';
    public const XPATH_GOOGLEPAY_INTEGRATION_MODE                  = 'integration_mode';
    public const XPATH_GOOGLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT = 'dont_ask_billing_info_in_checkout';
    public const XPATH_ACCOUNT_MERCHANT_GUID                       = 'merchant_guid';
    public const XPATH_GOOGLE_MERCHANT_ID                          = 'google_merchant_id';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
        'GBP'
    ];

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Repository            $assetRepo
     * @param ScopeConfigInterface  $scopeConfig
     * @param AllowedCurrencies     $allowedCurrencies
     * @param PaymentFee            $paymentFeeHelper
     * @param LogoService           $logoService
     * @param StoreManagerInterface $storeManager
     * @param Resolver              $localeResolver
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);

        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'storeName'                    => $this->getStoreName(),
            'merchantName'                 => $this->getStoreName(),
            'currency'                     => $this->getStoreCurrency(),
            'cultureCode'                  => $this->getCultureCode(),
            'country'                      => $this->getDefaultCountry(),
            'countryCode'                  => $this->getDefaultCountry(),
            'guid'                         => $this->getMerchantGuid(),
            'gatewayMerchantId'            => $this->getMerchantGuid(),
            'merchantId'                   => $this->getGoogleMerchantId(),
            'availableButtons'             => $this->getAvailableButtons(),
            'buttonStyle'                  => $this->getButtonStyle(),
            'dontAskBillingInfoInCheckout' => (int)$this->getDontAskBillingInfoInCheckout(),
            'integrationMode'              => $this->getIntegrationMode(),
            'locale'                       => $this->getCultureCode(),
            'allowedCardNetworks'          => ['AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA'],
        ]);
    }

    /**
     * Get Merchant Guid from Buckaroo Payment Engine
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getMerchantGuid($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_ACCOUNT_MERCHANT_GUID, $store);
    }

    /**
     * Get Google Pay Merchant ID
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getGoogleMerchantId($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GOOGLE_MERCHANT_ID, $store);
    }

    /**
     * Get available buttons configuration
     *
     * @return array
     */
    public function getAvailableButtons(): array
    {
        $availableButtons = $this->getMethodConfigValue(self::XPATH_GOOGLEPAY_AVAILABLE_BUTTONS);

        if ($availableButtons) {
            $availableButtons = explode(',', (string)$availableButtons);
        } else {
            $availableButtons = [];
        }

        return $availableButtons;
    }

    /**
     * Returns the button style configuration setting for the specified store.
     *
     * @param int|string|Store $store
     *
     * @return mixed
     */
    public function getButtonStyle($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GOOGLEPAY_BUTTON_STYLE, $store);
    }

    /**
     * Do not ask for billing info in checkout
     *
     * @param int|string|Store $store
     *
     * @return mixed
     */
    public function getDontAskBillingInfoInCheckout($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GOOGLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT, $store);
    }

    /**
     * Get base allowed currencies
     *
     * @return array
     */
    public function getBaseAllowedCurrencies(): array
    {
        return [
            'EUR',
            'USD',
            'GBP',
            'DKK',
            'NOK',
            'SEK',
            'CHF',
            'PLN',
            'HUF',
            'ISK',
            'JPY',
            'NZD',
            'RUB',
            'ZAR',
        ];
    }

    /**
     * Get Store Name
     *
     * @throws NoSuchEntityException
     *
     * @return string
     */
    public function getStoreName(): string
    {
        return $this->storeManager->getStore()->getName();
    }

    /**
     * Get Store Currency
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @return string
     */
    public function getStoreCurrency(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get Culture Code
     *
     * @return string
     */
    public function getCultureCode(): string
    {
        $localeCode = $this->localeResolver->getLocale();
        return explode('_', $localeCode)[0];
    }

    /**
     * Get default country
     *
     * @return mixed
     */
    public function getDefaultCountry()
    {
        return $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * Check if Google Pay is enabled
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isGooglepayEnabled()
    {
        return $this->getMethodConfigValue(self::XPATH_GOOGLEPAY_ACTIVE, $this->storeManager->getStore());
    }

    /**
     * Get Integration Mode
     */
    public function getIntegrationMode()
    {
        return $this->getMethodConfigValue(self::XPATH_GOOGLEPAY_INTEGRATION_MODE, $this->storeManager->getStore());
    }
}
