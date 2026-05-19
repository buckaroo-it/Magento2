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

class Multibanco extends AbstractConfigProvider
{
    public const XPATH_MULTIBANCO_PAYMENT_FEE           = 'payment/buckaroo_magento2_multibanco/payment_fee';
    public const XPATH_MULTIBANCO_ACTIVE                = 'payment/buckaroo_magento2_multibanco/active';
    public const XPATH_MULTIBANCO_SUBTEXT               = 'payment/buckaroo_magento2_multibanco/subtext';
    public const XPATH_MULTIBANCO_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_multibanco/subtext_style';
    public const XPATH_MULTIBANCO_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_multibanco/subtext_color';
    public const XPATH_MULTIBANCO_ACTIVE_STATUS         = 'payment/buckaroo_magento2_multibanco/active_status';
    public const XPATH_MULTIBANCO_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_multibanco/order_status_success';
    public const XPATH_MULTIBANCO_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_multibanco/order_status_failed';
    public const XPATH_MULTIBANCO_ORDER_EMAIL           = 'payment/buckaroo_magento2_multibanco/order_email';
    public const XPATH_MULTIBANCO_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_multibanco/available_in_backend';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_multibanco/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_multibanco/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_multibanco/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_multibanco/specificcustomergroup';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            self::XPATH_MULTIBANCO_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'multibanco' => [
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
            self::XPATH_MULTIBANCO_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: false;
    }
}
