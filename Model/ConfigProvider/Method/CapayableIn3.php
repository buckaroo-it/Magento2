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
use TIG\Buckaroo\Model\Method\Capayable\Installments as CapayableIn3Method;

/**
 * @method getPaymentFeeLabel()
 * @method getVersion()
 */
class CapayableIn3 extends AbstractConfigProvider
{
    const XPATH_CAPAYABLEIN3_PAYMENT_FEE          = 'payment/tig_buckaroo_capayablein3/payment_fee';
    const XPATH_CAPAYABLEIN3_PAYMENT_FEE_LABEL    = 'payment/tig_buckaroo_capayablein3/payment_fee_label';
    const XPATH_CAPAYABLEIN3_ACTIVE               = 'payment/tig_buckaroo_capayablein3/active';
    const XPATH_CAPAYABLEIN3_ACTIVE_STATUS        = 'payment/tig_buckaroo_capayablein3/active_status';
    const XPATH_CAPAYABLEIN3_ORDER_STATUS_SUCCESS = 'payment/tig_buckaroo_capayablein3/order_status_success';
    const XPATH_CAPAYABLEIN3_ORDER_STATUS_FAILED  = 'payment/tig_buckaroo_capayablein3/order_status_failed';
    const XPATH_CAPAYABLEIN3_ORDER_EMAIL          = 'payment/tig_buckaroo_capayablein3/order_email';
    const XPATH_CAPAYABLEIN3_AVAILABLE_IN_BACKEND = 'payment/tig_buckaroo_capayablein3/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_capayablein3/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/tig_buckaroo_capayablein3/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/tig_buckaroo_capayablein3/specificcountry';

    const XPATH_CAPAYABLEIN3_VERSION = 'payment/tig_buckaroo_capayablein3/version';

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
        if (!$this->scopeConfig->getValue(self::XPATH_CAPAYABLEIN3_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(CapayableIn3Method::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'capayablein3' => [
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
            self::XPATH_CAPAYABLEIN3_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
