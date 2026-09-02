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

use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;

class Afterpay20 extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_afterpay20';

    public const XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP = 'create_invoice_after_shipment';

    public const XPATH_AFTERPAY20_CUSTOMER_TYPE  = 'customer_type';
    public const XPATH_AFTERPAY20_MIN_AMOUNT_B2B = 'min_amount_b2b';
    public const XPATH_AFTERPAY20_MAX_AMOUNT_B2B = 'max_amount_b2b';
    public const XPATH_AFTERPAY20_SCA            = 'afterpay_sca';
    public const XPATH_AFTERPAY20_PAYMENT_FEE            = 'payment/buckaroo_magento2_afterpay20/payment_fee';

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return  $this->fullConfig([
            'sendEmail'            => $this->hasOrderEmail(),
            'is_b2b'               => $this->getCustomerType() !== AfterpayCustomerType::CUSTOMER_TYPE_B2C,
            'showFinancialWarning' => $this->canShowFinancialWarning(),
        ]);
    }

    /**
     * Get customer type
     *
     * @param null|int $storeId
     *
     * @return string
     */
    public function getCustomerType($storeId = null)
    {
        return $this->getMethodConfigValue(self::XPATH_AFTERPAY20_CUSTOMER_TYPE, $storeId);
    }

    /**
     * Create invoice after shipment
     *
     * @param null|int|string $storeId
     *
     * @return bool
     */
    public function isInvoiceCreatedAfterShipment($storeId = null): bool
    {
        $createInvoiceAfterShipment = $this->getMethodConfigValue(self::XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP, $storeId);

        return $createInvoiceAfterShipment ?: false;
    }

    /**
     * Get customer type
     *
     * @param null|int $storeId
     *
     * @return bool
     */
    public function isEnabledSCA($storeId = null): bool
    {
        return (bool)$this->getMethodConfigValue(self::XPATH_AFTERPAY20_SCA, $storeId);
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
            self::XPATH_AFTERPAY20_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
