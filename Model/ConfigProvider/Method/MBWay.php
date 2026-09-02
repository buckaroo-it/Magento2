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

class MBWay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_mbway';
    public const XPATH_MBWAY_PAYMENT_FEE           = 'payment/buckaroo_magento2_mbway/payment_fee';

    /**
     * Get the checkout configuration for MB WAY.
     *
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'sendEmail' => $this->hasOrderEmail(),
        ]);
    }

    /**
     * Get the payment fee configured for MB WAY.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_MBWAY_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
