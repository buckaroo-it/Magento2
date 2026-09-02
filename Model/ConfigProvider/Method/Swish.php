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

class Swish extends AbstractConfigProvider
{
    public const CODE                                = 'buckaroo_magento2_swish';
    public const XPATH_SWISH_PAYMENT_FEE           = 'payment/buckaroo_magento2_swish/payment_fee';

    /**
     * Get the payment fee configured for Swish.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_SWISH_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? : 0;
    }

    /**
     * Get the base allowed currencies for Swish.
     *
     * @return array
     */
    public function getBaseAllowedCurrencies(): array
    {
        return [
            'SEK',
        ];
    }
}
