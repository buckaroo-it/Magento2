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
    public const CODE = 'buckaroo_magento2_applepay';

    public const XPATH_APPLEPAY_AVAILABLE_BUTTONS = 'payment/buckaroo_magento2_applepay/available_buttons';
    public const XPATH_APPLEPAY_BUTTON_STYLE = 'payment/buckaroo_magento2_applepay/button_style';
    public const XPATH_APPLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT =
        'payment/buckaroo_magento2_applepay/dont_ask_billing_info_in_checkout';

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

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     * @param Account $configProvicerAccount
     */
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
     * @inheritDoc
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
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
                        'buttonStyle' => $this->getButtonStyle(),
                        'dontAskBillingInfoInCheckout' => (int) $this->getDontAskBillingInfoInCheckout()
                    ],
                ],
            ],
        ];
    }

    /**
     * @return false|string[]
     */
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

    public function getButtonStyle($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_BUTTON_STYLE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getDontAskBillingInfoInCheckout($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_APPLEPAY_DONT_ASK_BILLING_INFO_IN_CHECKOUT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
