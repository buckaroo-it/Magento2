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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

class Wechatpay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_wechatpay';
    public const XPATH_WECHATPAY_PAYMENT_FEE           = 'payment/buckaroo_magento2_wechatpay/payment_fee';

    /**
     * @inheritdoc
     */
    public function getBaseAllowedCurrencies(): array
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

    /**
     * Get the payment fee configured for this payment method.
     *
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

        return $paymentFee ?: 0;
    }
}
