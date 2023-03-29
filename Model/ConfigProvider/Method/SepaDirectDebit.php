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

class SepaDirectDebit extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_sepadirectdebit';

    public const XPATH_SEPADIRECTDEBIT_ACTIVE_STATUS_CM3 =
        'payment/buckaroo_magento2_sepadirectdebit/active_status_cm3';
    public const XPATH_SEPADIRECTDEBIT_SCHEME_KEY = 'payment/buckaroo_magento2_sepadirectdebit/scheme_key';
    public const XPATH_SEPADIRECTDEBIT_MAX_STEP_INDEX = 'payment/buckaroo_magento2_sepadirectdebit/max_step_index';
    public const XPATH_SEPADIRECTDEBIT_CM3_DUE_DATE = 'payment/buckaroo_magento2_sepadirectdebit/cm3_due_date';
    public const XPATH_SEPADIRECTDEBIT_PAYMENT_METHOD_AFTER_EXPIRY =
        'payment/buckaroo_magento2_sepadirectdebit/payment_method_after_expiry';

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'sepadirectdebit' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_SEPADIRECTDEBIT_ACTIVE_STATUS_CM3,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getSchemeKey($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_SEPADIRECTDEBIT_SCHEME_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_SEPADIRECTDEBIT_MAX_STEP_INDEX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getCm3DueDate($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_SEPADIRECTDEBIT_CM3_DUE_DATE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritdoc
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_SEPADIRECTDEBIT_PAYMENT_METHOD_AFTER_EXPIRY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
