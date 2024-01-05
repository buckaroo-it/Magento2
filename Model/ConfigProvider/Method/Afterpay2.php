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

use Buckaroo\Magento2\Model\Config\Source\Afterpay2PaymentMethods;
use Buckaroo\Magento2\Model\Config\Source\Business;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Afterpay2 extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_afterpay2/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_afterpay2/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_afterpay2/specificcountry';

    const XPATH_AFTERPAY2_ACTIVE                 = 'payment/buckaroo_magento2_afterpay2/active';
    const XPATH_AFTERPAY2_SUBTEXT                = 'payment/buckaroo_magento2_afterpay2/subtext';
    const XPATH_AFTERPAY2_SUBTEXT_STYLE          = 'payment/buckaroo_magento2_afterpay2/subtext_style';
    const XPATH_AFTERPAY2_SUBTEXT_COLOR          = 'payment/buckaroo_magento2_afterpay2/subtext_color';
    const XPATH_AFTERPAY2_PAYMENT_FEE            = 'payment/buckaroo_magento2_afterpay2/payment_fee';
    const XPATH_AFTERPAY2_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_afterpay2/payment_fee_label';
    const XPATH_AFTERPAY2_SEND_EMAIL             = 'payment/buckaroo_magento2_afterpay2/send_email';
    const XPATH_AFTERPAY2_ACTIVE_STATUS          = 'payment/buckaroo_magento2_afterpay2/active_status';
    const XPATH_AFTERPAY2_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_afterpay2/order_status_success';
    const XPATH_AFTERPAY2_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_afterpay2/order_status_failed';
    const XPATH_AFTERPAY2_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_afterpay2/available_in_backend';
    const XPATH_AFTERPAY2_DUE_DATE               = 'payment/buckaroo_magento2_afterpay2/due_date';
    const XPATH_AFTERPAY2_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_afterpay2/allowed_currencies';
    const XPATH_AFTERPAY2_BUSINESS               = 'payment/buckaroo_magento2_afterpay2/business';
    const XPATH_AFTERPAY2_PAYMENT_METHODS        = 'payment/buckaroo_magento2_afterpay2/payment_method';
    const XPATH_AFTERPAY2_HIGH_TAX               = 'payment/buckaroo_magento2_afterpay2/high_tax';
    const XPATH_AFTERPAY2_MIDDLE_TAX             = 'payment/buckaroo_magento2_afterpay2/middle_tax';
    const XPATH_AFTERPAY2_LOW_TAX                = 'payment/buckaroo_magento2_afterpay2/low_tax';
    const XPATH_AFTERPAY2_ZERO_TAX               = 'payment/buckaroo_magento2_afterpay2/zero_tax';
    const XPATH_AFTERPAY2_NO_TAX                 = 'payment/buckaroo_magento2_afterpay2/no_tax';

    const XPATH_SPECIFIC_CUSTOMER_GROUP          = 'payment/buckaroo_magento2_afterpay2/specificcustomergroup';
    const XPATH_SPECIFIC_CUSTOMER_GROUP_B2B      = 'payment/buckaroo_magento2_afterpay2/specificcustomergroupb2b';

    const XPATH_FINANCIAL_WARNING                = 'payment/buckaroo_magento2_afterpay2/financial_warning';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_AFTERPAY2_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Afterpay2::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay2' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
                        'paymentMethod'     => $this->getPaymentMethod(),
                        'showFinancialWarning' => $this->canShowFinancialWarning(self::XPATH_FINANCIAL_WARNING)
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
            self::XPATH_AFTERPAY2_BUSINESS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $paymentMethod = $this->getPaymentMethod();

        // Acceptgiro payment method is ALWAYS B2C
        if ($paymentMethod == Afterpay2PaymentMethods::PAYMENT_METHOD_ACCEPTGIRO) {
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
            self::XPATH_AFTERPAY2_PAYMENT_METHODS,
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
            self::XPATH_AFTERPAY2_HIGH_TAX,
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
            self::XPATH_AFTERPAY2_MIDDLE_TAX,
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
            self::XPATH_AFTERPAY2_LOW_TAX,
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
            self::XPATH_AFTERPAY2_ZERO_TAX,
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
            self::XPATH_AFTERPAY2_NO_TAX,
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
            self::XPATH_AFTERPAY2_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
