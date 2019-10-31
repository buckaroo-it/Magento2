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

namespace TIG\Buckaroo\Model\ConfigProvider\Method;

class Mrcash extends AbstractConfigProvider
{
    const XPATH_MRCASH_PAYMENT_FEE              = 'payment/tig_buckaroo_mrcash/payment_fee';
    const XPATH_MRCASH_PAYMENT_FEE_LABEL        = 'payment/tig_buckaroo_mrcash/payment_fee_label';
    const XPATH_MRCASH_ACTIVE                   = 'payment/tig_buckaroo_mrcash/active';
    const XPATH_MRCASH_ACTIVE_STATUS            = 'payment/tig_buckaroo_mrcash/active_status';
    const XPATH_MRCASH_ORDER_STATUS_SUCCESS     = 'payment/tig_buckaroo_mrcash/order_status_success';
    const XPATH_MRCASH_ORDER_STATUS_FAILED      = 'payment/tig_buckaroo_mrcash/order_status_failed';
    const XPATH_MRCASH_AVAILABLE_IN_BACKEND     = 'payment/tig_buckaroo_mrcash/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_mrcash/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/tig_buckaroo_mrcash/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/tig_buckaroo_mrcash/specificcountry';

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Mrcash::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'mrcash' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
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
            self::XPATH_MRCASH_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
