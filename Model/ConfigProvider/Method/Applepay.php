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
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Applepay extends AbstractConfigProvider
{
    const XPATH_APPLEPAY_ACTIVE                = 'payment/buckaroo_magento2_applepay/active';
    const XPATH_APPLEPAY_ACTIVE_STATUS         = 'payment/buckaroo_magento2_applepay/active_status';
    const XPATH_APPLEPAY_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_applepay/order_status_success';
    const XPATH_APPLEPAY_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_applepay/order_status_failed';
    const XPATH_APPLEPAY_ORDER_EMAIL           = 'payment/buckaroo_magento2_applepay/order_email';
    const XPATH_APPLEPAY_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_applepay/available_in_backend';
    const XPATH_APPLEPAY_AVAILABLE_BUTTONS     = 'payment/buckaroo_magento2_applepay/available_buttons';
    const XPATH_APPLEPAY_BUTTON_STYLE     = 'payment/buckaroo_magento2_applepay/button_style';
    const XPATH_APPLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT = 'payment/'.
        'buckaroo_magento2_applepay/dont_ask_billing_info_in_checkout';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_applepay/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/buckaroo_magento2_applepay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/buckaroo_magento2_applepay/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_applepay/specificcustomergroup';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
        'GBP'
    ];

    /** @var Account */
    private $configProvicerAccount;

    /** @var Resolver */
    private $localeResolver;

    /** @var StoreManagerInterface */
    private $storeManager;

    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        Account $configProvicerAccount
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->configProvicerAccount = $configProvicerAccount;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ACTIVE,
            ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $store = $this->storeManager->getStore();
        $storeName = $store->getName();
        $currency = $store->getCurrentCurrency()->getCode();

        $localeCode = $this->localeResolver->getLocale();
        $shortLocale = explode('_', $localeCode)[0];

        return [
            'payment' => [
                'buckaroo' => [
                    'applepay' => [
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'storeName' => $storeName,
                        'currency' => $currency,
                        'cultureCode' => $shortLocale,
                        'country' => $this->scopeConfig->getValue(
                            'general/country/default',
                            ScopeInterface::SCOPE_WEBSITES
                        ),
                        'guid' => $this->configProvicerAccount->getMerchantGuid(),
                        'availableButtons' => $this->getAvailableButtons(),
                        'buttonStyle' => $this->scopeConfig->getValue(
                            static::XPATH_APPLEPAY_BUTTON_STYLE,
                            ScopeInterface::SCOPE_STORE
                        ),
                        'dontAskBillingInfoInCheckout' => (int) $this->scopeConfig->getValue(
                            static::XPATH_APPLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT,
                            ScopeInterface::SCOPE_STORE
                        )
                    ],
                ],
            ],
        ];
    }

    public function getAvailableButtons()
    {
        $availableButtons = $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_AVAILABLE_BUTTONS,
            ScopeInterface::SCOPE_STORE
        );
        if ($availableButtons) {
            $availableButtons = explode(',', (string)$availableButtons);
        } else {
            $availableButtons = false;
        }

        return $availableButtons;
    }


    /**
     * {@inheritdoc}
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveStatus($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ACTIVE_STATUS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ORDER_STATUS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_ORDER_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getButtonStyle($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_BUTTON_STYLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDontAskBillingInfoInCheckout($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
