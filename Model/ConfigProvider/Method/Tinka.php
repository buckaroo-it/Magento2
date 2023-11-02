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

class Tinka extends AbstractConfigProvider
{
    const XPATH_TINKA_PAYMENT_FEE           = 'payment/buckaroo_magento2_tinka/payment_fee';
    const XPATH_TINKA_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_tinka/payment_fee_label';
    const XPATH_TINKA_ACTIVE                = 'payment/buckaroo_magento2_tinka/active';
    const XPATH_TINKA_SUBTEXT               = 'payment/buckaroo_magento2_tinka/subtext';
    const XPATH_TINKA_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_tinka/subtext_style';
    const XPATH_TINKA_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_tinka/subtext_color';
    const XPATH_TINKA_ACTIVE_STATUS         = 'payment/buckaroo_magento2_tinka/active_status';
    const XPATH_TINKA_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_tinka/order_status_success';
    const XPATH_TINKA_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_tinka/order_status_failed';
    const XPATH_TINKA_ORDER_EMAIL           = 'payment/buckaroo_magento2_tinka/order_email';
    const XPATH_TINKA_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_tinka/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_tinka/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_tinka/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_tinka/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_tinka/specificcustomergroup';
    const XPATH_ACTIVE_SERVICE                  = 'payment/buckaroo_magento2_tinka/activeservice';
    const XPATH_FINANCIAL_WARNING               = 'payment/buckaroo_magento2_tinka/financial_warning';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_TINKA_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Tinka::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'tinka' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'showFinancialWarning' => $this->canShowFinancialWarning(self::XPATH_FINANCIAL_WARNING)
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
            self::XPATH_TINKA_PAYMENT_FEE,
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
        ];
    }
}
