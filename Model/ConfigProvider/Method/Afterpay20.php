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

use TIG\Buckaroo\Model\Method\Afterpay20 as Afterpay20Method;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Afterpay20 extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/tig_buckaroo_afterpay20/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                   = 'payment/tig_buckaroo_afterpay20/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                 = 'payment/tig_buckaroo_afterpay20/specificcountry';

    const XPATH_AFTERPAY20_ACTIVE                 = 'payment/tig_buckaroo_afterpay20/active';
    const XPATH_AFTERPAY20_PAYMENT_FEE            = 'payment/tig_buckaroo_afterpay20/payment_fee';
    const XPATH_AFTERPAY20_PAYMENT_FEE_LABEL      = 'payment/tig_buckaroo_afterpay20/payment_fee_label';
    const XPATH_AFTERPAY20_SEND_EMAIL             = 'payment/tig_buckaroo_afterpay20/send_email';
    const XPATH_AFTERPAY20_ACTIVE_STATUS          = 'payment/tig_buckaroo_afterpay20/active_status';
    const XPATH_AFTERPAY20_ORDER_STATUS_SUCCESS   = 'payment/tig_buckaroo_afterpay20/order_status_success';
    const XPATH_AFTERPAY20_ORDER_STATUS_FAILED    = 'payment/tig_buckaroo_afterpay20/order_status_failed';
    const XPATH_AFTERPAY20_AVAILABLE_IN_BACKEND   = 'payment/tig_buckaroo_afterpay20/available_in_backend';
    const XPATH_AFTERPAY20_DUE_DATE               = 'payment/tig_buckaroo_afterpay20/due_date';
    const XPATH_AFTERPAY20_ALLOWED_CURRENCIES     = 'payment/tig_buckaroo_afterpay20/allowed_currencies';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_AFTERPAY20_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(Afterpay20Method::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay20' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
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
            self::XPATH_AFTERPAY20_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
