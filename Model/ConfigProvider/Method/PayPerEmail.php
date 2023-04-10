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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Exception;
use Magento\Store\Model\ScopeInterface;

class PayPerEmail extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_payperemail';

    public const XPATH_PAYPEREMAIL_ACTIVE_STATUS_CM3 = 'payment/buckaroo_magento2_payperemail/active_status_cm3';
    public const XPATH_PAYPEREMAIL_SEND_MAIL                   = 'payment/buckaroo_magento2_payperemail/send_mail';
    public const XPATH_PAYPEREMAIL_SCHEME_KEY                  = 'payment/buckaroo_magento2_payperemail/scheme_key';
    public const XPATH_PAYPEREMAIL_MAX_STEP_INDEX              = 'payment/buckaroo_magento2_payperemail/max_step_index';
    public const XPATH_PAYPEREMAIL_CM3_DUE_DATE                = 'payment/buckaroo_magento2_payperemail/cm3_due_date';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD              = 'payment/buckaroo_magento2_payperemail/payment_method';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD_AFTER_EXPIRY =
        'payment/buckaroo_magento2_payperemail/payment_method_after_expiry';
    public const XPATH_PAYPEREMAIL_VISIBLE_FRONT_BACK = 'payment/buckaroo_magento2_payperemail/visible_front_back';
    public const XPATH_PAYPEREMAIL_ENABLE_B2B                  = 'payment/buckaroo_magento2_payperemail/enable_b2b';
    public const XPATH_PAYPEREMAIL_EXPIRE_DAYS                 = 'payment/buckaroo_magento2_payperemail/expire_days';
    public const XPATH_PAYPEREMAIL_CANCEL_PPE                  = 'payment/buckaroo_magento2_payperemail/cancel_ppe';
    public const XPATH_PAYPEREMAIL_CRON_CANCEL_PPE = 'payment/buckaroo_magento2_payperemail/cron_cancel_ppe';

    /**
     * Retrieve PayPerEmail assoc array of checkout configuration
     *
     * @return array
     * @throws Exception
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'payperemail' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'genderList' => [
                            ['genderType' => 1, 'genderTitle' => __('He/him')],
                            ['genderType' => 2, 'genderTitle' => __('She/her')],
                            ['genderType' => 0, 'genderTitle' => __('They/them')],
                            ['genderType' => 9, 'genderTitle' => __('I prefer not to say')]
                        ]
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * Sends an email to the customer with the payment procedures.
     *
     * @return bool
     */
    public function hasSendMail(): bool
    {
        $sendMail = $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_SEND_MAIL,
            ScopeInterface::SCOPE_STORE
        );

        return (bool)$sendMail;
    }

    /**
     * Get payment methods available for pay per email
     *
     * @param int|null $storeId
     * @return false|mixed
     */
    public function getPaymentMethod(int $storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_PAYMENT_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: false;
    }

    /**
     * B2B mode enabled
     *
     * @return bool
     */
    public function isEnabledB2B()
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_ENABLE_B2B,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is enable or disable auto cancelling by cron
     *
     * @return mixed
     */
    public function getEnabledCronCancelPPE()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_CRON_CANCEL_PPE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the expiration date for the paylink
     *
     * @return integer
     */
    public function getExpireDays()
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_EXPIRE_DAYS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Cancel PPE link after order is cancel in Magento
     *
     * @return mixed
     */
    public function getCancelPpe()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_PAYPEREMAIL_CANCEL_PPE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if PayPerEmail is visible for specific area code
     *
     * @param string $areaCode
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

    /**
     * Check if Credit Management is enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_ACTIVE_STATUS_CM3,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Credit Management Scheme Key
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSchemeKey($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_SCHEME_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Max level of the Credit Management steps
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_MAX_STEP_INDEX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get credit managment due date, amount of days after the order date
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getCm3DueDate($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_CM3_DUE_DATE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get payment method which can be used after the payment due date.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_PAYMENT_METHOD_AFTER_EXPIRY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get where the Pay Per Email method will be visible Frontend or Backend or Both
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getVisibleFrontBack($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_VISIBLE_FRONT_BACK,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get status of B2B mode enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getEnableB2b($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYPEREMAIL_ENABLE_B2B,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
