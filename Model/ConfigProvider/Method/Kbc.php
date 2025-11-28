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

class Kbc extends AbstractConfigProvider
{
    public const XPATH_KBC_PAYMENT_FEE           = 'payment/buckaroo_magento2_kbc/payment_fee';
    public const XPATH_KBC_ACTIVE                = 'payment/buckaroo_magento2_kbc/active';
    public const XPATH_KBC_SUBTEXT               = 'payment/buckaroo_magento2_kbc/subtext';
    public const XPATH_KBC_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_kbc/subtext_style';
    public const XPATH_KBC_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_kbc/subtext_color';
    public const XPATH_KBC_ACTIVE_STATUS         = 'payment/buckaroo_magento2_kbc/active_status';
    public const XPATH_KBC_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_kbc/order_status_success';
    public const XPATH_KBC_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_kbc/order_status_failed';
    public const XPATH_KBC_ORDER_EMAIL           = 'payment/buckaroo_magento2_kbc/order_email';
    public const XPATH_KBC_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_kbc/available_in_backend';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_kbc/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_kbc/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_kbc/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_kbc/specificcustomergroup';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            self::XPATH_KBC_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'kbc' => [
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
            self::XPATH_KBC_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
