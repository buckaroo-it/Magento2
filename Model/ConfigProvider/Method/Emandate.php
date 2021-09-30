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

use \Magento\Store\Model\ScopeInterface;

class Emandate extends AbstractConfigProvider
{
    const XPATH_EMANDATE_PAYMENT_FEE           = 'payment/buckaroo_magento2_emandate/payment_fee';
    const XPATH_EMANDATE_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_emandate/payment_fee_label';
    const XPATH_EMANDATE_ACTIVE                = 'payment/buckaroo_magento2_emandate/active';
    const XPATH_EMANDATE_ACTIVE_STATUS         = 'payment/buckaroo_magento2_emandate/active_status';
    const XPATH_EMANDATE_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_emandate/order_status_success';
    const XPATH_EMANDATE_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_emandate/order_status_failed';
    const XPATH_EMANDATE_ORDER_EMAIL           = 'payment/buckaroo_magento2_emandate/order_email';
    const XPATH_EMANDATE_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_emandate/available_in_backend';

    const XPATH_EMANDATE_SEQUENCE_TYPE         = 'payment/buckaroo_magento2_emandate/sequence_type';
    const XPATH_EMANDATE_REASON                = 'payment/buckaroo_magento2_emandate/reason';
    const XPATH_EMANDATE_LANGUAGE              = 'payment/buckaroo_magento2_emandate/language';

    const XPATH_ALLOWED_CURRENCIES             = 'payment/buckaroo_magento2_emandate/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                 = 'payment/buckaroo_magento2_emandate/allowspecific';
    const XPATH_SPECIFIC_COUNTRY               = 'payment/buckaroo_magento2_emandate/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP        = 'payment/buckaroo_magento2_emandate/specificcustomergroup';

    /**
     * @var array
     */
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
    ];

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_EMANDATE_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Emandate::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'emandate' => [
                        'banks' => $issuers,
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
            static::XPATH_EMANDATE_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_PAYMENT_FEE_LABEL,
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
            static::XPATH_EMANDATE_ACTIVE,
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
            static::XPATH_EMANDATE_ACTIVE_STATUS,
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
            static::XPATH_EMANDATE_ORDER_STATUS_SUCCESS,
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
            static::XPATH_EMANDATE_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_ORDER_EMAIL,
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
            static::XPATH_EMANDATE_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceType($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_SEQUENCE_TYPE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getReason($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_REASON,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_LANGUAGE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
