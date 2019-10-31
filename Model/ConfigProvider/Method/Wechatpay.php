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

class Wechatpay extends AbstractConfigProvider
{
    const XPATH_WECHATPAY_PAYMENT_FEE           = 'payment/tig_buckaroo_wechatpay/payment_fee';
    const XPATH_WECHATPAY_PAYMENT_FEE_LABEL     = 'payment/tig_buckaroo_wechatpay/payment_fee_label';
    const XPATH_WECHATPAY_ACTIVE                = 'payment/tig_buckaroo_wechatpay/active';
    const XPATH_WECHATPAY_ACTIVE_STATUS         = 'payment/tig_buckaroo_wechatpay/active_status';
    const XPATH_WECHATPAY_ORDER_STATUS_SUCCESS  = 'payment/tig_buckaroo_wechatpay/order_status_success';
    const XPATH_WECHATPAY_ORDER_STATUS_FAILED   = 'payment/tig_buckaroo_wechatpay/order_status_failed';
    const XPATH_WECHATPAY_AVAILABLE_IN_BACKEND  = 'payment/tig_buckaroo_wechatpay/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_wechatpay/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/tig_buckaroo_wechatpay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/tig_buckaroo_wechatpay/specificcountry';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this
            ->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Wechatpay::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'wechatpay' => [
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
            self::XPATH_WECHATPAY_PAYMENT_FEE,
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
            'GBP',
            'HKD',
            'JPY',
            'CAD',
            'AUD',
            'NZD',
            'KRW',
            'THB',
            'SGD',
            'RUB',
        ];
    }
}
