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

class Giropay extends AbstractConfigProvider
{
    const XPATH_GIROPAY_PAYMENT_FEE             = 'payment/buckaroo_magento2_giropay/payment_fee';
    const XPATH_GIROPAY_PAYMENT_FEE_LABEL       = 'payment/buckaroo_magento2_giropay/payment_fee_label';
    const XPATH_GIROPAY_ACTIVE                  = 'payment/buckaroo_magento2_giropay/active';
    const XPATH_GIROPAY_SUBTEXT                 = 'payment/buckaroo_magento2_giropay/subtext';
    const XPATH_GIROPAY_SUBTEXT_STYLE           = 'payment/buckaroo_magento2_giropay/subtext_style';
    const XPATH_GIROPAY_SUBTEXT_COLOR           = 'payment/buckaroo_magento2_giropay/subtext_color';
    const XPATH_GIROPAY_ACTIVE_STATUS           = 'payment/buckaroo_magento2_giropay/active_status';
    const XPATH_GIROPAY_ORDER_STATUS_SUCCESS    = 'payment/buckaroo_magento2_giropay/order_status_success';
    const XPATH_GIROPAY_ORDER_STATUS_FAILED     = 'payment/buckaroo_magento2_giropay/order_status_failed';
    const XPATH_GIROPAY_AVAILABLE_IN_BACKEND    = 'payment/buckaroo_magento2_giropay/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_giropay/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_giropay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_giropay/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP         = 'payment/buckaroo_magento2_giropay/specificcustomergroup';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Giropay::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'giropay' => [
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
            self::XPATH_GIROPAY_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
