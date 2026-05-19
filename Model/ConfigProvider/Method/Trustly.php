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

class Trustly extends AbstractConfigProvider
{
    public const XPATH_TRUSTLY_PAYMENT_FEE           = 'payment/buckaroo_magento2_trustly/payment_fee';
    public const XPATH_TRUSTLY_ACTIVE                = 'payment/buckaroo_magento2_trustly/active';
    public const XPATH_TRUSTLY_SUBTEXT               = 'payment/buckaroo_magento2_trustly/subtext';
    public const XPATH_TRUSTLY_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_trustly/subtext_style';
    public const XPATH_TRUSTLY_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_trustly/subtext_color';
    public const XPATH_TRUSTLY_ACTIVE_STATUS         = 'payment/buckaroo_magento2_trustly/active_status';
    public const XPATH_TRUSTLY_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_trustly/order_status_success';
    public const XPATH_TRUSTLY_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_trustly/order_status_failed';
    public const XPATH_TRUSTLY_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_trustly/available_in_backend';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_trustly/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_trustly/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_trustly/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_trustly/specificcustomergroup';

    protected $allowedCountries = [
        'DE',
        'DK',
        'EE',
        'ES',
        'FI',
        'NL',
        'NO',
        'PL',
        'SE',
        'GB',
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'trustly' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
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
            self::XPATH_TRUSTLY_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * @return array
     */
    public function getBaseAllowedCurrencies()
    {
        return [
            'EUR',
            'GBP',
            'PLN',
            'SEK',
            'DKK',
            'NOK',
        ];
    }
}
