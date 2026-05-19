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
 * @method getCm3DueDate()
 * @method getMaxStepIndex()
 * @method getPaymentMethodAfterExpiry()
 * @method getSchemeKey()
 * @method getActiveStatusCm3()
 */
class PayPerEmail extends AbstractConfigProvider
{
    public const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_payperemail/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_payperemail/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_payperemail/specificcountry';

    public const XPATH_PAYPEREMAIL_ACTIVE               = 'payment/buckaroo_magento2_payperemail/active';
    public const XPATH_PAYPEREMAIL_SUBTEXT              = 'payment/buckaroo_magento2_payperemail/subtext';
    public const XPATH_PAYPEREMAIL_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_payperemail/subtext_style';
    public const XPATH_PAYPEREMAIL_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_payperemail/subtext_color';
    public const XPATH_PAYPEREMAIL_PAYMENT_FEE          = 'payment/buckaroo_magento2_payperemail/payment_fee';
    public const XPATH_PAYPEREMAIL_ACTIVE_STATUS        = 'payment/buckaroo_magento2_payperemail/active_status';
    public const XPATH_PAYPEREMAIL_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_payperemail/order_status_success';
    public const XPATH_PAYPEREMAIL_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_payperemail/order_status_failed';

    public const XPATH_PAYPEREMAIL_ACTIVE_STATUS_CM3           = 'payment/buckaroo_magento2_payperemail/active_status_cm3';
    public const XPATH_PAYPEREMAIL_SEND_MAIL                   = 'payment/buckaroo_magento2_payperemail/send_mail';
    public const XPATH_PAYPEREMAIL_SCHEME_KEY                  = 'payment/buckaroo_magento2_payperemail/scheme_key';
    public const XPATH_PAYPEREMAIL_MAX_STEP_INDEX              = 'payment/buckaroo_magento2_payperemail/max_step_index';
    public const XPATH_PAYPEREMAIL_CM3_DUE_DATE                = 'payment/buckaroo_magento2_payperemail/cm3_due_date';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD              = 'payment/buckaroo_magento2_payperemail/payment_method';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD_AFTER_EXPIRY = 'payment/'.
        'buckaroo_magento2_payperemail/payment_method_after_expiry';
    public const XPATH_PAYPEREMAIL_VISIBLE_FRONT_BACK          = 'payment/buckaroo_magento2_payperemail/visible_front_back';
    public const XPATH_PAYPEREMAIL_IS_VISIBLE_FOR_AREA_CODE = 'payment/buckaroo_magento2_payperemail/is_visible_for_area_code';
    public const XPATH_PAYPEREMAIL_ENABLE_B2B                  = 'payment/buckaroo_magento2_payperemail/enable_b2b';
    public const XPATH_PAYPEREMAIL_EXPIRE_DAYS                 = 'payment/buckaroo_magento2_payperemail/expire_days';
    public const XPATH_PAYPEREMAIL_CANCEL_PPE                  = 'payment/buckaroo_magento2_payperemail/cancel_ppe';
    public const XPATH_PAYPEREMAIL_CRON_CANCEL_PPE             = 'payment/buckaroo_magento2_payperemail/cron_cancel_ppe';

    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_payperemail/specificcustomergroup';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP_B2B     = 'payment/buckaroo_magento2_payperemail/specificcustomergroupb2b';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'payperemail' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'genderList' => [
                            ['genderType' => 1, 'genderTitle' => __('He/him')],
                            ['genderType' => 2, 'genderTitle' => __('She/her')],
                            ['genderType' => 0, 'genderTitle' => __('They/them')],
                            ['genderType' => 9, 'genderTitle' => __('I prefer not to say')],
                        ],
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
            self::XPATH_PAYPEREMAIL_PAYMENT_FEE,
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
            self::XPATH_PAYPEREMAIL_SEND_MAIL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $sendMail ? true : false;
    }

    public function getPaymentMethod($storeId = null)
    {
        $paymentMethod = $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_PAYMENT_METHOD,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentMethod ? $paymentMethod : false;
    }

    /**
     * @return bool
     */
    public function getEnabledB2B()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_ENABLE_B2B,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getEnabledCronCancelPPE()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_CRON_CANCEL_PPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return int
     */
    public function getExpireDays()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_EXPIRE_DAYS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCancelPpe()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_CANCEL_PPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getActive()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param       $areaCode
     * @return bool
     */
    public function isVisibleForAreaCode($areaCode)
    {
        if (null === $this->getVisibleFrontBack()) {
            return false;
        }

        $forFrontend = ('frontend' === $this->getVisibleFrontBack() || 'both' === $this->getVisibleFrontBack());
        $forBackend = ('backend' === $this->getVisibleFrontBack() || 'both' === $this->getVisibleFrontBack());

        if (($areaCode == 'adminhtml' && !$forBackend) || ($areaCode != 'adminhtml' && !$forFrontend)) {
            return false;
        }

        return true;
    }
}
