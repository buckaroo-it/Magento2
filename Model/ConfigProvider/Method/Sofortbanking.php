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

class Sofortbanking extends AbstractConfigProvider
{
    const XPATH_SOFORTBANKING_PAYMENT_FEE           = 'payment/buckaroo_magento2_sofortbanking/payment_fee';
    const XPATH_SOFORTBANKING_PAYMENT_FEE_LABEL     = 'payment/buckaroo_magento2_sofortbanking/payment_fee_label';
    const XPATH_SOFORTBANKING_ACTIVE                = 'payment/buckaroo_magento2_sofortbanking/active';
    const XPATH_SOFORTBANKING_SUBTEXT               = 'payment/buckaroo_magento2_sofortbanking/subtext';
    const XPATH_SOFORTBANKING_SUBTEXT_STYLE         = 'payment/buckaroo_magento2_sofortbanking/subtext_style';
    const XPATH_SOFORTBANKING_SUBTEXT_COLOR         = 'payment/buckaroo_magento2_sofortbanking/subtext_color';
    const XPATH_SOFORTBANKING_ACTIVE_STATUS         = 'payment/buckaroo_magento2_sofortbanking/active_status';
    const XPATH_SOFORTBANKING_ORDER_STATUS_SUCCESS  = 'payment/buckaroo_magento2_sofortbanking/order_status_success';
    const XPATH_SOFORTBANKING_ORDER_STATUS_FAILED   = 'payment/buckaroo_magento2_sofortbanking/order_status_failed';
    const XPATH_SOFORTBANKING_AVAILABLE_IN_BACKEND  = 'payment/buckaroo_magento2_sofortbanking/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_sofortbanking/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_sofortbanking/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_sofortbanking/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_sofortbanking/specificcustomergroup';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Sofortbanking::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'sofortbanking' => [
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
            self::XPATH_SOFORTBANKING_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
