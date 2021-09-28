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

use Magento\Store\Model\ScopeInterface;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Klarna extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES            = 'buckaroo/buckaroo_magento2_klarna/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC                = 'payment/buckaroo_magento2_klarna/allowspecific';
    const XPATH_SPECIFIC_COUNTRY              = 'payment/buckaroo_magento2_klarna/specificcountry';
    const XPATH_KLARNA_ACTIVE                 = 'payment/buckaroo_magento2_klarna/active';
    const XPATH_KLARNA_PAYMENT_FEE            = 'payment/buckaroo_magento2_klarna/payment_fee';
    const XPATH_KLARNA_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_klarna/payment_fee_label';
    const XPATH_KLARNA_SEND_EMAIL             = 'payment/buckaroo_magento2_klarna/send_email';
    const XPATH_KLARNA_ACTIVE_STATUS          = 'payment/buckaroo_magento2_klarna/active_status';
    const XPATH_KLARNA_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_klarna/order_status_success';
    const XPATH_KLARNA_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_klarna/order_status_failed';
    const XPATH_KLARNA_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_klarna/available_in_backend';
    const XPATH_KLARNA_DUE_DATE               = 'payment/buckaroo_magento2_klarna/due_date';
    const XPATH_KLARNA_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_klarna/allowed_currencies';
    const XPATH_KLARNA_BUSINESS               = 'payment/buckaroo_magento2_klarna/business';
    const XPATH_KLARNA_PAYMENT_METHODS        = 'payment/buckaroo_magento2_klarna/payment_method';
    const XPATH_KLARNA_HIGH_TAX               = 'payment/buckaroo_magento2_klarna/high_tax';
    const XPATH_KLARNA_MIDDLE_TAX             = 'payment/buckaroo_magento2_klarna/middle_tax';
    const XPATH_KLARNA_LOW_TAX                = 'payment/buckaroo_magento2_klarna/low_tax';
    const XPATH_KLARNA_ZERO_TAX               = 'payment/buckaroo_magento2_klarna/zero_tax';
    const XPATH_KLARNA_NO_TAX                 = 'payment/buckaroo_magento2_klarna/no_tax';
    const XPATH_KLARNA_GET_INVOICE            = 'payment/buckaroo_magento2_klarna/send_invoice';
    const XPATH_SPECIFIC_CUSTOMER_GROUP       = 'payment/buckaroo_magento2_klarna/specificcustomergroup';

    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_KLARNA_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\Buckaroo\Magento2\Model\Method\Klarna\PayLater::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarna' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
                        'paymentMethod'     => $this->getPaymentMethod(),
                        'paymentFee'        => $this->getPaymentFee(),
                    ],
                    'response' => [],
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
            static::XPATH_KLARNA_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : 0;
    }

    public function getInvoiceSendMethod($storeId = null)
    {
        return $this->getConfigFromXpath(static::XPATH_KLARNA_GET_INVOICE, $storeId);
    }

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getEnabled($storeId = null)
    {
        $enabled = $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $enabled ? $enabled : false;
    }


    /**
     * {@inheritdoc}
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSendEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_SEND_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveStatus($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ACTIVE_STATUS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ORDER_STATUS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDueDate($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_DUE_DATE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBusiness($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_BUSINESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethod($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_PAYMENT_METHODS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getHighTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_HIGH_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddleTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_MIDDLE_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLowTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_LOW_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getZeroTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_ZERO_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getNoTax($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_NO_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getGetInvoice($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_KLARNA_GET_INVOICE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
