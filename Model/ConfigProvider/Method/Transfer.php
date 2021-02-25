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
 * @method getDueDate()
 * @method getActiveStatusCm3()
 * @method getSchemeKey()
 * @method getMaxStepIndex()
 * @method getCm3DueDate()
 * @method getPaymentMethodAfterExpiry()
 */
class Transfer extends AbstractConfigProvider
{
    const XPATH_TRANSFER_ACTIVE                 = 'payment/buckaroo_magento2_transfer/active';
    const XPATH_TRANSFER_PAYMENT_FEE            = 'payment/buckaroo_magento2_transfer/payment_fee';
    const XPATH_TRANSFER_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_transfer/payment_fee_label';
    const XPATH_TRANSFER_SEND_EMAIL             = 'payment/buckaroo_magento2_transfer/send_email';
    const XPATH_TRANSFER_ACTIVE_STATUS          = 'payment/buckaroo_magento2_transfer/active_status';
    const XPATH_TRANSFER_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_transfer/order_status_success';
    const XPATH_TRANSFER_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_transfer/order_status_failed';
    const XPATH_TRANSFER_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_transfer/available_in_backend';
    const XPATH_TRANSFER_DUE_DATE               = 'payment/buckaroo_magento2_transfer/due_date';

    const XPATH_TRANSFER_ACTIVE_STATUS_CM3           = 'payment/buckaroo_magento2_transfer/active_status_cm3';
    const XPATH_TRANSFER_SCHEME_KEY                  = 'payment/buckaroo_magento2_transfer/scheme_key';
    const XPATH_TRANSFER_MAX_STEP_INDEX              = 'payment/buckaroo_magento2_transfer/max_step_index';
    const XPATH_TRANSFER_CM3_DUE_DATE                = 'payment/buckaroo_magento2_transfer/cm3_due_date';
    const XPATH_TRANSFER_PAYMENT_METHOD_AFTER_EXPIRY = 'payment/buckaroo_magento2_transfer/payment_method_after_expiry';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_transfer/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_transfer/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_transfer/specificcountry';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            self::XPATH_TRANSFER_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Transfer::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'transfer' => [
                        'sendEmail' => (bool) $this->getSendEmail(),
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ]
                ]
            ]
        ];
    }

    /**
     * @return string
     */
    public function getSendEmail()
    {
        $sendMail = $this->scopeConfig->getValue(
            self::XPATH_TRANSFER_SEND_EMAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $sendMail ? 'true' : 'false';
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_TRANSFER_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
