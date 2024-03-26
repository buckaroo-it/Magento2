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

class PayPerEmail extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_payperemail';

    public const XPATH_PAYPEREMAIL_SEND_MAIL = 'send_mail';

    public const XPATH_PAYPEREMAIL_ACTIVE_STATUS_CM3           = 'active_status_cm3';
    public const XPATH_PAYPEREMAIL_SCHEME_KEY                  = 'scheme_key';
    public const XPATH_PAYPEREMAIL_MAX_STEP_INDEX              = 'max_step_index';
    public const XPATH_PAYPEREMAIL_CM3_DUE_DATE                = 'cm3_due_date';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD              = 'payment_method';
    public const XPATH_PAYPEREMAIL_PAYMENT_METHOD_AFTER_EXPIRY = 'payment_method_after_expiry';
    public const XPATH_PAYPEREMAIL_VISIBLE_FRONT_BACK          = 'visible_front_back';
    public const XPATH_PAYPEREMAIL_ENABLE_B2B                  = 'enable_b2b';
    public const XPATH_PAYPEREMAIL_EXPIRE_DAYS                 = 'expire_days';
    public const XPATH_PAYPEREMAIL_CANCEL_PPE                  = 'cancel_ppe';
    public const XPATH_PAYPEREMAIL_CRON_CANCEL_PPE             = 'cron_cancel_ppe';

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

        return [
            'payment' => [
                'buckaroo' => [
                    'payperemail' => [
                        'paymentFeeLabel'   => $this->getBuckarooPaymentFeeLabel(),
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'genderList'        => [
                            ['genderType' => 1, 'genderTitle' => __('He/him')],
                            ['genderType' => 2, 'genderTitle' => __('She/her')],
                            ['genderType' => 0, 'genderTitle' => __('They/them')],
                            ['genderType' => 9, 'genderTitle' => __('I prefer not to say')]
                        ],
                        'isTestMode'        => $this->isTestMode()
                    ],
                    'response'    => [],
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
        return (bool)$this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_SEND_MAIL);
    }

    /**
     * Get payment methods available for pay per email
     *
     * @param int|null $storeId
     * @return false|mixed
     */
    public function getPaymentMethod(int $storeId = null)
    {
        $paymentMethod = $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_PAYMENT_METHOD, $storeId);

        return $paymentMethod ?: false;
    }

    /**
     * B2B mode enabled
     *
     * @return bool
     */
    public function isEnabledB2B()
    {
        return $this->getMethodConfigValue(static::XPATH_PAYPEREMAIL_ENABLE_B2B);
    }

    /**
     * Is enable or disable auto cancelling by cron
     *
     * @return mixed
     */
    public function getEnabledCronCancelPPE()
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_CRON_CANCEL_PPE);
    }

    /**
     * Get the expiration date for the paylink
     *
     * @return integer
     */
    public function getExpireDays()
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_EXPIRE_DAYS);
    }

    /**
     * Cancel PPE link after order is cancel in Magento
     *
     * @return mixed
     */
    public function getCancelPpe()
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_CANCEL_PPE);
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
     * Get where the Pay Per Email method will be visible Frontend or Backend or Both
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getVisibleFrontBack($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_VISIBLE_FRONT_BACK, $store);
    }

    /**
     * Check if Credit Management is enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_ACTIVE_STATUS_CM3, $store);
    }

    /**
     * Credit Management Scheme Key
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSchemeKey($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_SCHEME_KEY, $store);

    }

    /**
     * Get Max level of the Credit Management steps
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_MAX_STEP_INDEX, $store);
    }

    /**
     * Get credit managment due date, amount of days after the order date
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getCm3DueDate($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_CM3_DUE_DATE, $store);
    }

    /**
     * Get payment method which can be used after the payment due date.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_PAYMENT_METHOD_AFTER_EXPIRY, $store);
    }

    /**
     * Get status of B2B mode enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getEnableB2b($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYPEREMAIL_ENABLE_B2B, $store);
    }
}
