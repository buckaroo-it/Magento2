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

class Afterpay2 extends Afterpay
{
    public const CODE = 'buckaroo_magento2_afterpay2';

    public const XPATH_AFTERPAY2_PAYMENT_FEE            = 'payment/buckaroo_magento2_afterpay2/payment_fee';

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'sendEmail'            => $this->hasOrderEmail(),
            'businessMethod'       => $this->getBusiness(),
            'paymentMethod'        => $this->getPaymentMethod(),
            'showFinancialWarning' => $this->canShowFinancialWarning(),
        ]);
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
            self::XPATH_AFTERPAY2_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
