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

use Magento\Store\Model\ScopeInterface;

class CapayableIn3 extends AbstractConfigProvider
{
    public const XPATH_CAPAYABLEIN3_PAYMENT_FEE          = 'payment/buckaroo_magento2_capayablein3/payment_fee';
    public const XPATH_CAPAYABLEIN3_ACTIVE               = 'payment/buckaroo_magento2_capayablein3/active';
    public const XPATH_CAPAYABLEIN3_SUBTEXT              = 'payment/buckaroo_magento2_capayablein3/subtext';
    public const XPATH_CAPAYABLEIN3_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_capayablein3/subtext_style';
    public const XPATH_CAPAYABLEIN3_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_capayablein3/subtext_color';
    public const XPATH_CAPAYABLEIN3_ACTIVE_STATUS        = 'payment/buckaroo_magento2_capayablein3/active_status';
    public const XPATH_CAPAYABLEIN3_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_capayablein3/order_status_success';
    public const XPATH_CAPAYABLEIN3_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_capayablein3/order_status_failed';
    public const XPATH_CAPAYABLEIN3_ORDER_EMAIL          = 'payment/buckaroo_magento2_capayablein3/order_email';
    public const XPATH_CAPAYABLEIN3_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_capayablein3/available_in_backend';

    public const XPATH_CAPAYABLEIN3_API_VERSION = 'payment/buckaroo_magento2_capayablein3/api_version';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_capayablein3/allowed_currencies';
    public const XPATH_ALLOW_SPECIFIC     = 'payment/buckaroo_magento2_capayablein3/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY   = 'payment/buckaroo_magento2_capayablein3/specificcountry';

    public const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_capayablein3/specificcustomergroup';

    public const XPATH_FINANCIAL_WARNING = 'payment/buckaroo_magento2_capayablein3/financial_warning';


    /** @var array */
    protected $allowedCurrencies = [
        'EUR',
    ];

    protected $allowedCountries = [
        'NL',
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(self::XPATH_CAPAYABLEIN3_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'capayablein3' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'logo' => $this->getLogo(),
                        'showFinancialWarning' => $this->canShowFinancialWarning(self::XPATH_FINANCIAL_WARNING),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    public function isV2($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_API_VERSION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === 'V2';
    }

    public function getLogo($storeId = null): string
    {
        if ($this->isV2($storeId)) {
            return 'in3.svg';
        }
        return 'ideal-in3.svg';
    }
}
