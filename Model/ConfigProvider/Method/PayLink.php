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

use Buckaroo\Magento2\Model\Method\PayLink as MethodPayLink;

/**
 * @method getCm3DueDate()
 * @method getMaxStepIndex()
 * @method getPaymentMethod()
 * @method getPaymentMethodAfterExpiry()
 * @method getSchemeKey()
 * @method getActiveStatusCm3()
 */
class PayLink extends AbstractConfigProvider
{
    const CODE = 'buckaroo_magento2_paylink';
    const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_paylink/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_paylink/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_paylink/specificcountry';

    const XPATH_PAYLINK_ACTIVE               = 'payment/buckaroo_magento2_paylink/active';
    const XPATH_PAYLINK_PAYMENT_FEE          = 'payment/buckaroo_magento2_paylink/payment_fee';
    const XPATH_PAYLINK_PAYMENT_FEE_LABEL    = 'payment/buckaroo_magento2_paylink/payment_fee_label';
    const XPATH_PAYLINK_ACTIVE_STATUS        = 'payment/buckaroo_magento2_paylink/active_status';
    const XPATH_PAYLINK_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_paylink/order_status_success';
    const XPATH_PAYLINK_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_paylink/order_status_failed';

    const XPATH_PAYLINK_ACTIVE_STATUS_CM3           = 'payment/buckaroo_magento2_paylink/active_status_cm3';
    const XPATH_PAYLINK_SEND_MAIL                   = 'payment/buckaroo_magento2_paylink/send_mail';
    const XPATH_PAYLINK_SCHEME_KEY                  = 'payment/buckaroo_magento2_paylink/scheme_key';
    const XPATH_PAYLINK_MAX_STEP_INDEX              = 'payment/buckaroo_magento2_paylink/max_step_index';
    const XPATH_PAYLINK_CM3_DUE_DATE                = 'payment/buckaroo_magento2_paylink/cm3_due_date';
    const XPATH_PAYLINK_PAYMENT_METHOD              = 'payment/buckaroo_magento2_paylink/payment_method';
    const XPATH_PAYLINK_PAYMENT_METHOD_AFTER_EXPIRY = 'payment/buckaroo_magento2_paylink/payment_method_after_expiry';
    const XPATH_PAYLINK_VISIBLE_FRONT_BACK          = 'payment/buckaroo_magento2_paylink/visible_front_back';
    const XPATH_PAYLINK_IS_VISIBLE_FOR_AREA_CODE    = 'payment/buckaroo_magento2_paylink/is_visible_for_area_code';

    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_paylink/specificcustomergroup';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_PAYLINK_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(MethodPayLink::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'paylink' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
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
            self::XPATH_PAYLINK_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * @return bool
     */
    public function getSendMail()
    {
        $sendMail = $this->scopeConfig->getValue(
            self::XPATH_PAYLINK_SEND_MAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $sendMail ? true : false;
    }

    /**
     * @param $areaCode
     * @return bool
     */
    public function isVisibleForAreaCode($areaCode)
    {
        if ($areaCode == 'adminhtml') {
            return true;
        }

        return false;
    }
}
