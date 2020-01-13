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

/**
 * @method getPaymentFeeLabel()
 * @method getSellersProtection()
 * @method getSellersProtectionEligible()
 * @method getSellersProtectionIneligible()
 * @method getSellersProtectionItemnotreceivedEligible()
 * @method getSellersProtectionUnauthorizedpaymentEligible()
 */
class Paypal extends AbstractConfigProvider
{
    const XPATH_PAYPAL_PAYMENT_FEE                      = 'payment/buckaroo_magento2_paypal/payment_fee';
    const XPATH_PAYPAL_PAYMENT_FEE_LABEL                = 'payment/buckaroo_magento2_paypal/payment_fee_label';
    const XPATH_PAYPAL_ACTIVE                           = 'payment/buckaroo_magento2_paypal/active';
    const XPATH_PAYPAL_ACTIVE_STATUS                    = 'payment/buckaroo_magento2_paypal/active_status';
    const XPATH_PAYPAL_ORDER_STATUS_SUCCESS             = 'payment/buckaroo_magento2_paypal/order_status_success';
    const XPATH_PAYPAL_ORDER_STATUS_FAILED              = 'payment/buckaroo_magento2_paypal/order_status_failed';
    const XPATH_PAYPAL_AVAILABLE_IN_BACKEND             = 'payment/buckaroo_magento2_paypal/available_in_backend';
    const XPATH_PAYPAL_SELLERS_PROTECTION               = 'payment/buckaroo_magento2_paypal/sellers_protection';
    const XPATH_PAYPAL_SELLERS_PROTECTION_ELIGIBLE      = 'payment/buckaroo_magento2_paypal/sellers_protection_eligible';
    const XPATH_PAYPAL_SELLERS_PROTECTION_INELIGIBLE    = 'payment/buckaroo_magento2_paypal/sellers_protection_ineligible';
    const XPATH_PAYPAL_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/buckaroo_magento2_paypal/sellers_protection_itemnotreceived_eligible';
    const XPATH_PAYPAL_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/buckaroo_magento2_paypal/sellers_protection_unauthorizedpayment_eligible';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_paypal/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_paypal/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_paypal/specificcountry';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\Buckaroo\Magento2\Model\Method\Paypal::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'paypal' => [
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
            self::XPATH_PAYPAL_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
