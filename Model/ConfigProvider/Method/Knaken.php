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

class Knaken extends AbstractConfigProvider
{
    const XPATH_KNAKEN_PAYMENT_FEE          = 'payment/buckaroo_magento2_knaken/payment_fee';
    const XPATH_KNAKEN_ACTIVE               = 'payment/buckaroo_magento2_knaken/active';
    const XPATH_KNAKEN_SUBTEXT              = 'payment/buckaroo_magento2_knaken/subtext';
    const XPATH_KNAKEN_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_knaken/subtext_style';
    const XPATH_KNAKEN_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_knaken/subtext_color';
    const XPATH_KNAKEN_ACTIVE_STATUS        = 'payment/buckaroo_magento2_knaken/active_status';
    const XPATH_KNAKEN_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_knaken/order_status_success';
    const XPATH_KNAKEN_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_knaken/order_status_failed';
    const XPATH_KNAKEN_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_knaken/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_knaken/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC          = 'payment/buckaroo_magento2_knaken/allowspecific';
    const XPATH_SPECIFIC_COUNTRY        = 'payment/buckaroo_magento2_knaken/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_knaken/specificcustomergroup';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'knaken' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'isTestMode'        => $this->isTestMode()
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
            self::XPATH_KNAKEN_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
