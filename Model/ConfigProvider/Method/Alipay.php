<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class Alipay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_alipay';

    public const XPATH_ALIPAY_PAYMENT_FEE           = 'payment/buckaroo_magento2_alipay/payment_fee';

    /**
     * Get the base allowed currencies for Alipay.
     *
     * @return array
     */
    public function getBaseAllowedCurrencies(): array
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

    /**
     * Get the payment fee configured for Alipay.
     *
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

        return $paymentFee ?: 0;
    }
}
