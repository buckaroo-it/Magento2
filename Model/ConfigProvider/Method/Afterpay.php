<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\Config\Source\AfterpayPaymentMethods;
use Buckaroo\Magento2\Model\Config\Source\Business;
use Magento\Store\Model\ScopeInterface;

class Afterpay extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_afterpay';

    public const XPATH_AFTERPAY_PAYMENT_METHODS = 'payment/buckaroo_magento2_afterpay/payment_method';
    public const XPATH_AFTERPAY_BUSINESS = 'payment/buckaroo_magento2_afterpay/business';

    public const XPATH_AFTERPAY_HIGH_TAX = 'payment/buckaroo_magento2_afterpay/high_tax';
    public const XPATH_AFTERPAY_MIDDLE_TAX = 'payment/buckaroo_magento2_afterpay/middle_tax';
    public const XPATH_AFTERPAY_LOW_TAX = 'payment/buckaroo_magento2_afterpay/low_tax';
    public const XPATH_AFTERPAY_ZERO_TAX = 'payment/buckaroo_magento2_afterpay/zero_tax';
    public const XPATH_AFTERPAY_NO_TAX = 'payment/buckaroo_magento2_afterpay/no_tax';

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay' => [
                        'sendEmail'         => $this->hasOrderEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
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
     * This setting determines whether you accept Riverty | Afterpay payments for B2C, B2B or both customer types
     * businessMethod 1 = B2C
     * businessMethod 2 = B2B
     * businessMethod 3 = Both
     *
     * @return bool|int
     */
    public function getBusiness()
    {
        $business = (int) $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_BUSINESS,
            ScopeInterface::SCOPE_STORE
        );

        $paymentMethod = $this->getPaymentMethod();

        // Acceptgiro payment method is ALWAYS B2C
        if ($paymentMethod == AfterpayPaymentMethods::PAYMENT_METHOD_ACCEPTGIRO) {
            $business = Business::BUSINESS_B2C;
        }

        return $business ?: false;
    }

    /**
     * Payment Method Channel
     * paymentMethod 1 = afterpayacceptgiro
     * paymentMethod 2 = afterpaydigiaccept
     *
     * @return bool|int
     */
    public function getPaymentMethod()
    {
        $paymentMethod = (int) $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_PAYMENT_METHODS,
            ScopeInterface::SCOPE_STORE
        );

        return $paymentMethod ?: false;
    }

    /**
     * Get the config values for the high tax classes.
     *
     * @param null|int|string $store
     * @return bool|mixed
     */
    public function getHighTaxClasses($store = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_HIGH_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $taxClasses ?: false;
    }

    /**
     * Get the config values for the middle tax classes
     *
     * @param null|int|string $store
     * @return bool|mixed
     */
    public function getMiddleTaxClasses($store = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_MIDDLE_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $taxClasses ?: false;
    }

    /**
     * Get the config values for the low tax classes
     *
     * @param null|int|string $store
     * @return bool|mixed
     */
    public function getLowTaxClasses($store = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_LOW_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $taxClasses ?: false;
    }

    /**
     * Get the config values for the zero tax classes
     *
     * @param null|int|string $store
     * @return bool|mixed
     */
    public function getZeroTaxClasses($store = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_ZERO_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $taxClasses ?: false;
    }

    /**
     * Get the config values for the no tax classes
     *
     * @param null|int|string $store
     * @return bool|mixed
     */
    public function getNoTaxClasses($store = null)
    {
        $taxClasses = $this->scopeConfig->getValue(
            static::XPATH_AFTERPAY_NO_TAX,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $taxClasses ?: false;
    }

    /**
     * Get the methods name
     *
     * @param int|string $method
     * @return bool|string
     */
    public function getPaymentMethodName($method = null)
    {
        $paymentMethodName = false;

        if (!$method) {
            $method = $this->getPaymentMethod();
        }

        if ($method) {
            if ($method == '1') {
                $paymentMethodName = 'afterpayacceptgiro';
            } elseif ($method == '2') {
                $paymentMethodName = 'afterpaydigiaccept';
            }
        }

        return $paymentMethodName;
    }
}
