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
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies;

class Applepay extends AbstractConfigProvider
{
    const XPATH_APPLEPAY_ACTIVE                = 'payment/tig_buckaroo_applepay/active';
    const XPATH_APPLEPAY_ACTIVE_STATUS         = 'payment/tig_buckaroo_applepay/active_status';
    const XPATH_APPLEPAY_ORDER_STATUS_SUCCESS  = 'payment/tig_buckaroo_applepay/order_status_success';
    const XPATH_APPLEPAY_ORDER_STATUS_FAILED   = 'payment/tig_buckaroo_applepay/order_status_failed';
    const XPATH_APPLEPAY_ORDER_EMAIL           = 'payment/tig_buckaroo_applepay/order_email';
    const XPATH_APPLEPAY_AVAILABLE_IN_BACKEND  = 'payment/tig_buckaroo_applepay/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_applepay/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/tig_buckaroo_applepay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/tig_buckaroo_applepay/specificcountry';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
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
                        'guid' => $this->configProvicerAccount->getMerchantGuid(),
                    ],
                ],
            ],
        ];
    }
}
