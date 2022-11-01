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
 * @method getSendEmail()
 */
class Klarnain extends AbstractConfigProvider
{
    const CODE = 'buckaroo_magento2_klarnain';

    const XPATH_ALLOWED_CURRENCIES            = 'buckaroo/buckaroo_magento2_klarnain/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC                = 'payment/buckaroo_magento2_klarnain/allowspecific';
    const XPATH_SPECIFIC_COUNTRY              = 'payment/buckaroo_magento2_klarnain/specificcountry';
    const XPATH_KLARNAIN_ACTIVE                 = 'payment/buckaroo_magento2_klarnain/active';
    const XPATH_KLARNAIN_PAYMENT_FEE            = 'payment/buckaroo_magento2_klarnain/payment_fee';
    const XPATH_KLARNAIN_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_klarnain/payment_fee_label';
    const XPATH_KLARNAIN_SEND_EMAIL             = 'payment/buckaroo_magento2_klarnain/send_email';
    const XPATH_KLARNAIN_ACTIVE_STATUS          = 'payment/buckaroo_magento2_klarnain/active_status';
    const XPATH_KLARNAIN_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_klarnain/order_status_success';
    const XPATH_KLARNAIN_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_klarnain/order_status_failed';
    const XPATH_KLARNAIN_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_klarnain/available_in_backend';
    const XPATH_KLARNAIN_DUE_DATE               = 'payment/buckaroo_magento2_klarnain/due_date';
    const XPATH_KLARNAIN_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_klarnain/allowed_currencies';
    const XPATH_KLARNAIN_BUSINESS               = 'payment/buckaroo_magento2_klarnain/business';
    const XPATH_KLARNAIN_PAYMENT_METHODS        = 'payment/buckaroo_magento2_klarnain/payment_method';
    const XPATH_KLARNAIN_HIGH_TAX               = 'payment/buckaroo_magento2_klarnain/high_tax';
    const XPATH_KLARNAIN_MIDDLE_TAX             = 'payment/buckaroo_magento2_klarnain/middle_tax';
    const XPATH_KLARNAIN_LOW_TAX                = 'payment/buckaroo_magento2_klarnain/low_tax';
    const XPATH_KLARNAIN_ZERO_TAX               = 'payment/buckaroo_magento2_klarnain/zero_tax';
    const XPATH_KLARNAIN_NO_TAX                 = 'payment/buckaroo_magento2_klarnain/no_tax';
    const XPATH_KLARNAIN_GET_INVOICE            = 'payment/buckaroo_magento2_klarnain/send_invoice';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_klarnain/specificcustomergroup';

    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_KLARNAIN_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarnain' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
                        'paymentMethod'     => $this->getPaymentMethod(),
                        'paymentFee'        => $this->getPaymentFee(),
                        'genderList' => [
                            ['genderType' => 'male', 'genderTitle' => 'He/him'],
                            ['genderType' => 'female', 'genderTitle' => 'She/her'],
                            ['genderType' => 'unknown', 'genderTitle' => 'They/them'],
                            ['genderType' => 'unknown', 'genderTitle' => 'I prefer not to say']
                        ]
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
            self::XPATH_KLARNAIN_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : 0;
    }

    public function getInvoiceSendMethod($storeId = null)
    {
        return $this->getConfigFromXpath(static::XPATH_KLARNAIN_GET_INVOICE, $storeId);
    }

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getEnabled($storeId = null)
    {
        $enabled = $this->scopeConfig->getValue(
            self::XPATH_KLARNAIN_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $enabled ? $enabled : false;
    }
}
