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

class Pospayment extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_pospayment';

    public const XPATH_POSPAYMENT_OTHER_PAYMENT_METHODS = 'other_payment_methods';
    public const XPATH_POSPAYMENT_PAYMENT_FEE           = 'payment/buckaroo_magento2_pospayment/payment_fee';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig();
    }

    /**
     * Get Other payment methods for POS
     *
     * @param null|int|string $store
     *
     * @return mixed|null
     */
    public function getOtherPaymentMethods($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_POSPAYMENT_OTHER_PAYMENT_METHODS, $store);
    }

    /**
     * Get the payment fee configured for POS payment.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_POSPAYMENT_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
