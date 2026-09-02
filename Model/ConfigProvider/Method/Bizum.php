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

class Bizum extends AbstractConfigProvider
{
    public const CODE                                = 'buckaroo_magento2_bizum';
    public const XPATH_BIZUM_PAYMENT_FEE           = 'payment/buckaroo_magento2_bizum/payment_fee';

    /**
     * Get the payment fee configured for Bizum.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_BIZUM_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? : 0;
    }

    /**
     * Get the base allowed currencies for Bizum.
     *
     * @return array
     */
    public function getBaseAllowedCurrencies(): array
    {
        return [
            'EUR',
        ];
    }
}
