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
 * @method getSellersProtection()
 * @method getSellersProtectionEligible()
 * @method getSellersProtectionIneligible()
 * @method getSellersProtectionItemnotreceivedEligible()
 * @method getSellersProtectionUnauthorizedpaymentEligible()
 */
class Paypal extends AbstractConfigProvider
{
    public const XPATH_PAYPAL_PAYMENT_FEE                      = 'payment/buckaroo_magento2_paypal/payment_fee';
    public const XPATH_PAYPAL_ACTIVE                           = 'payment/buckaroo_magento2_paypal/active';
    public const XPATH_PAYPAL_SUBTEXT                          = 'payment/buckaroo_magento2_paypal/subtext';
    public const XPATH_PAYPAL_SUBTEXT_STYLE                    = 'payment/buckaroo_magento2_paypal/subtext_style';
    public const XPATH_PAYPAL_SUBTEXT_COLOR                    = 'payment/buckaroo_magento2_paypal/subtext_color';
    public const XPATH_PAYPAL_ACTIVE_STATUS                    = 'payment/buckaroo_magento2_paypal/active_status';
    public const XPATH_PAYPAL_ORDER_STATUS_SUCCESS             = 'payment/buckaroo_magento2_paypal/order_status_success';
    public const XPATH_PAYPAL_ORDER_STATUS_FAILED              = 'payment/buckaroo_magento2_paypal/order_status_failed';
    public const XPATH_PAYPAL_AVAILABLE_IN_BACKEND             = 'payment/buckaroo_magento2_paypal/available_in_backend';
    public const XPATH_PAYPAL_SELLERS_PROTECTION               = 'payment/buckaroo_magento2_paypal/sellers_protection';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_ELIGIBLE      = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_eligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_INELIGIBLE    = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_ineligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_ITEMNOTRECEIVED_ELIGIBLE = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_itemnotreceived_eligible';
    public const XPATH_PAYPAL_SELLERS_PROTECTION_UNAUTHORIZEDPAYMENT_ELIGIBLE = 'payment/' .
        'buckaroo_magento2_paypal/sellers_protection_unauthorizedpayment_eligible';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_paypal/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_paypal/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_paypal/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_paypal/specificcustomergroup';

    public const XPATH_PAYPAL_EXPRESS_BUTTONS          = 'payment/buckaroo_magento2_paypal/available_buttons';
    public const XPATH_PAYPAL_EXPRESS_MERCHANT_ID          = 'payment/buckaroo_magento2_paypal/express_merchant_id';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'paypal' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
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
    public function getExpressButtons($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_BUTTONS, $store);
    }
    public function getExpressMerchantId($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_EXPRESS_MERCHANT_ID, $store);
    }
    /**
     * Test if express button is enabled for the $page
     *
     * @param string     $page
     * @param null|mixed $store
     *
     * @return bool
     */
    public function canShowButtonForPage($page, $store = null)
    {
        $buttons = $this->getExpressButtons($store);
        if ($buttons === null) {
            return false;
        }

        $pages = explode(",", $buttons);
        return in_array($page, $pages);
    }
    public function isPayPalEnabled($store = null)
    {
        return $this->getConfigFromXpath(self::XPATH_PAYPAL_ACTIVE, $store);
    }
}
