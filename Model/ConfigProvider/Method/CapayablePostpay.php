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

use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\Method\Capayable\Postpay as CapayablePostpayMethod;

/**
 * @method getPaymentFeeLabel()
 */
class CapayablePostpay extends AbstractConfigProvider
{
    const XPATH_CAPAYABLEPOSTPAY_PAYMENT_FEE          = 'payment/tig_buckaroo_capayablepostpay/payment_fee';
    const XPATH_CAPAYABLEPOSTPAY_PAYMENT_FEE_LABEL    = 'payment/tig_buckaroo_capayablepostpay/payment_fee_label';
    const XPATH_CAPAYABLEPOSTPAY_ACTIVE               = 'payment/tig_buckaroo_capayablepostpay/active';
    const XPATH_CAPAYABLEPOSTPAY_ACTIVE_STATUS        = 'payment/tig_buckaroo_capayablepostpay/active_status';
    const XPATH_CAPAYABLEPOSTPAY_ORDER_STATUS_SUCCESS = 'payment/tig_buckaroo_capayablepostpay/order_status_success';
    const XPATH_CAPAYABLEPOSTPAY_ORDER_STATUS_FAILED  = 'payment/tig_buckaroo_capayablepostpay/order_status_failed';
    const XPATH_CAPAYABLEPOSTPAY_ORDER_EMAIL          = 'payment/tig_buckaroo_capayablepostpay/order_email';
    const XPATH_CAPAYABLEPOSTPAY_AVAILABLE_IN_BACKEND = 'payment/tig_buckaroo_capayablepostpay/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_capayablepostpay/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/tig_buckaroo_capayablepostpay/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/tig_buckaroo_capayablepostpay/specificcountry';

    /** @var array */
    protected $allowedCurrencies = [
        'EUR'
    ];

    protected $allowedCountries = [
        'NL'
    ];

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(self::XPATH_CAPAYABLEPOSTPAY_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(CapayablePostpayMethod::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'capayablepostpay' => [
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
            self::XPATH_CAPAYABLEPOSTPAY_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
