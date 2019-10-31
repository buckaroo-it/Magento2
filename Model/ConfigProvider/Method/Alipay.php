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

class Alipay extends AbstractConfigProvider
{
    const XPATH_ALIPAY_PAYMENT_FEE           = 'payment/tig_buckaroo_alipay/payment_fee';
    const XPATH_ALIPAY_PAYMENT_FEE_LABEL     = 'payment/tig_buckaroo_alipay/payment_fee_label';
    const XPATH_ALIPAY_ACTIVE                = 'payment/tig_buckaroo_alipay/active';
    const XPATH_ALIPAY_ACTIVE_STATUS         = 'payment/tig_buckaroo_alipay/active_status';
    const XPATH_ALIPAY_ORDER_STATUS_SUCCESS  = 'payment/tig_buckaroo_alipay/order_status_success';
    const XPATH_ALIPAY_ORDER_STATUS_FAILED   = 'payment/tig_buckaroo_alipay/order_status_failed';
    const XPATH_ALIPAY_AVAILABLE_IN_BACKEND  = 'payment/tig_buckaroo_alipay/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_alipay/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/tig_buckaroo_alipay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/tig_buckaroo_alipay/specificcountry';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this
            ->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Alipay::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'alipay' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
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
            self::XPATH_ALIPAY_PAYMENT_FEE,
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
            'USD',
            'JPY',
            'GBP',
            'CAD',
            'AUD',
            'SGD',
            'CHF',
            'SEK',
            'DKK',
            'NOK',
            'NZD',
            'THB',
            'HKD'
        ];
    }
}
