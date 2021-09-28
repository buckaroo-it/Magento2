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

class Trustly extends AbstractConfigProvider
{
    const XPATH_TRUSTLY_PAYMENT_FEE           = 'payment/buckaroo_magento2_trustly/payment_fee';
    const XPATH_TRUSTLY_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_trustly/payment_fee_label';
    const XPATH_TRUSTLY_ACTIVE                = 'payment/buckaroo_magento2_trustly/active';
    const XPATH_TRUSTLY_ACTIVE_STATUS         = 'payment/buckaroo_magento2_trustly/active_status';
    const XPATH_TRUSTLY_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_trustly/order_status_success';
    const XPATH_TRUSTLY_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_trustly/order_status_failed';
    const XPATH_TRUSTLY_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_trustly/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_trustly/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_trustly/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_trustly/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_trustly/specificcustomergroup';

    protected $allowedCountries = [
        'DE',
        'DK',
        'EE',
        'ES',
        'FI',
        'NL',
        'NO',
        'PL',
        'SE',
        'GB'
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this
            ->getBuckarooPaymentFeeLabel(\Buckaroo\Magento2\Model\Method\Trustly::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'trustly' => [
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
            static::XPATH_TRUSTLY_PAYMENT_FEE,
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
            'GBP',
            'PLN',
            'SEK',
            'DKK',
            'NOK',
        ];
    }



    /**
     * {@inheritdoc}
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRUSTLY_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRUSTLY_ACTIVE,
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
            static::XPATH_TRUSTLY_ACTIVE_STATUS,
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
            static::XPATH_TRUSTLY_ORDER_STATUS_SUCCESS,
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
            static::XPATH_TRUSTLY_ORDER_STATUS_FAILED,
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
            static::XPATH_TRUSTLY_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
