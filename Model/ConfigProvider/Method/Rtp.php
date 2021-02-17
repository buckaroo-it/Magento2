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

class Rtp extends AbstractConfigProvider
{
    const XPATH_RTP_PAYMENT_FEE           = 'payment/buckaroo_magento2_rtp/payment_fee';
    const XPATH_RTP_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_rtp/payment_fee_label';
    const XPATH_RTP_ACTIVE                = 'payment/buckaroo_magento2_rtp/active';
    const XPATH_RTP_ACTIVE_STATUS         = 'payment/buckaroo_magento2_rtp/active_status';
    const XPATH_RTP_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_rtp/order_status_success';
    const XPATH_RTP_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_rtp/order_status_failed';
    const XPATH_RTP_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_rtp/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_rtp/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_rtp/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_rtp/specificcountry';

    protected $allowedCountries = [
        'DE',
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this
            ->getBuckarooPaymentFeeLabel(\Buckaroo\Magento2\Model\Method\Rtp::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'rtp' => [
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
            self::XPATH_RTP_PAYMENT_FEE,
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
