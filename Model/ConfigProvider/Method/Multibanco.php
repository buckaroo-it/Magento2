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
    const XPATH_MULTIBANCO_PAYMENT_FEE           = 'payment/buckaroo_magento2_multibanco/payment_fee';
    const XPATH_MULTIBANCO_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_multibanco/payment_fee_label';
    const XPATH_MULTIBANCO_ACTIVE                = 'payment/buckaroo_magento2_multibanco/active';
    const XPATH_MULTIBANCO_SUBTEXT               = 'payment/buckaroo_magento2_multibanco/subtext';
    const XPATH_MULTIBANCO_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_multibanco/subtext_style';
    const XPATH_MULTIBANCO_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_multibanco/subtext_color';
    const XPATH_MULTIBANCO_ACTIVE_STATUS         = 'payment/buckaroo_magento2_multibanco/active_status';
    const XPATH_MULTIBANCO_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_multibanco/order_status_success';
    const XPATH_MULTIBANCO_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_multibanco/order_status_failed';
    const XPATH_MULTIBANCO_ORDER_EMAIL           = 'payment/buckaroo_magento2_multibanco/order_email';
    const XPATH_MULTIBANCO_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_multibanco/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_multibanco/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_multibanco/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_multibanco/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_multibanco/specificcustomergroup';

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

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Multibanco::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'multibanco' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ]
                ]
            ]
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
