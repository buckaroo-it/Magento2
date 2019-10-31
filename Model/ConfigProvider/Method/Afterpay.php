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

namespace TIG\Buckaroo\Model\ConfigProvider\Method;

use TIG\Buckaroo\Model\Config\Source\AfterpayPaymentMethods;
use TIG\Buckaroo\Model\Config\Source\Business;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Afterpay extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES              = 'buckaroo/tig_buckaroo_afterpay/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/tig_buckaroo_afterpay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/tig_buckaroo_afterpay/specificcountry';

    const XPATH_AFTERPAY_ACTIVE                 = 'payment/tig_buckaroo_afterpay/active';
    const XPATH_AFTERPAY_PAYMENT_FEE            = 'payment/tig_buckaroo_afterpay/payment_fee';
    const XPATH_AFTERPAY_PAYMENT_FEE_LABEL      = 'payment/tig_buckaroo_afterpay/payment_fee_label';
    const XPATH_AFTERPAY_SEND_EMAIL             = 'payment/tig_buckaroo_afterpay/send_email';
    const XPATH_AFTERPAY_ACTIVE_STATUS          = 'payment/tig_buckaroo_afterpay/active_status';
    const XPATH_AFTERPAY_ORDER_STATUS_SUCCESS   = 'payment/tig_buckaroo_afterpay/order_status_success';
    const XPATH_AFTERPAY_ORDER_STATUS_FAILED    = 'payment/tig_buckaroo_afterpay/order_status_failed';
    const XPATH_AFTERPAY_AVAILABLE_IN_BACKEND   = 'payment/tig_buckaroo_afterpay/available_in_backend';
    const XPATH_AFTERPAY_DUE_DATE               = 'payment/tig_buckaroo_afterpay/due_date';
    const XPATH_AFTERPAY_ALLOWED_CURRENCIES     = 'payment/tig_buckaroo_afterpay/allowed_currencies';
    const XPATH_AFTERPAY_BUSINESS               = 'payment/tig_buckaroo_afterpay/business';
    const XPATH_AFTERPAY_PAYMENT_METHODS        = 'payment/tig_buckaroo_afterpay/payment_method';
    const XPATH_AFTERPAY_HIGH_TAX               = 'payment/tig_buckaroo_afterpay/high_tax';
    const XPATH_AFTERPAY_MIDDLE_TAX             = 'payment/tig_buckaroo_afterpay/middle_tax';
    const XPATH_AFTERPAY_LOW_TAX                = 'payment/tig_buckaroo_afterpay/low_tax';
    const XPATH_AFTERPAY_ZERO_TAX               = 'payment/tig_buckaroo_afterpay/zero_tax';
    const XPATH_AFTERPAY_NO_TAX                 = 'payment/tig_buckaroo_afterpay/no_tax';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Afterpay::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
                        'paymentMethod'     => $this->getPaymentMethod(),
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * businessMethod 1 = B2C
     * businessMethod 2 = B2B
     * businessMethod 3 = Both
     *
     * @return bool|int
     */
    public function getBusiness()
    {
        $business = (int) $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_BUSINESS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $paymentMethod = $this->getPaymentMethod();

        // Acceptgiro payment method is ALWAYS B2C
        if ($paymentMethod == AfterpayPaymentMethods::PAYMENT_METHOD_ACCEPTGIRO) {
            $business = Business::BUSINESS_B2C;
        }

        return $business ? $business : false;
    }

    /**
     * paymentMethod 1 = afterpayacceptgiro
     * paymentMethod 2 = afterpaydigiaccept
     *
     * @return bool|int
     */
    public function getPaymentMethod()
    {
        $paymentMethod = (int) $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_PAYMENT_METHODS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $paymentMethod ? $paymentMethod : false;
    }

    /**
     * Get the config values for the high tax classes.
     *
     * @param null|int $storeId
     *
     * @return bool|mixed
     */
    public function getHighTaxClasses($storeId = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_HIGH_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $taxClasses ? $taxClasses : false;
    }

    /**
     * Get the config values for the middle tax classes
     *
     * @param null|int $storeId
     *
     * @return bool|mixed
     */
    public function getMiddleTaxClasses($storeId = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_MIDDLE_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $taxClasses ? $taxClasses : false;
    }

    /**
     * Get the config values for the low tax classes
     *
     * @param null|int $storeId
     *
     * @return bool|mixed
     */
    public function getLowTaxClasses($storeId = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_LOW_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $taxClasses ? $taxClasses : false;
    }

    /**
     * Get the config values for the zero tax classes
     *
     * @param null|int $storeId
     *
     * @return bool|mixed
     */
    public function getZeroTaxClasses($storeId = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_ZERO_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $taxClasses ? $taxClasses : false;
    }

    /**
     * Get the config values for the no tax classes
     *
     * @return bool|mixed
     */
    public function getNoTaxClasses()
    {
        $taxClasses = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_NO_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $taxClasses ? $taxClasses : false;
    }

    /**
     * Get the methods name
     *
     * @param int $method
     *
     * @return bool|string
     */
    public function getPaymentMethodName($method = null)
    {
        $paymentMethodName = false;

        if (!$method) {
            $method = $this->getPaymentMethod();
        }

        if ($method) {
            switch ($method) {
                case '1':
                    $paymentMethodName = 'afterpayacceptgiro';
                    break;
                case '2':
                    $paymentMethodName = 'afterpaydigiaccept';
            }
        }

        return $paymentMethodName;
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
