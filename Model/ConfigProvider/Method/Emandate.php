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

class Emandate extends AbstractConfigProvider
{
    const XPATH_EMANDATE_PAYMENT_FEE           = 'payment/tig_buckaroo_emandate/payment_fee';
    const XPATH_EMANDATE_PAYMENT_FEE_LABEL     = 'payment/tig_buckaroo_emandate/payment_fee_label';
    const XPATH_EMANDATE_ACTIVE                = 'payment/tig_buckaroo_emandate/active';
    const XPATH_EMANDATE_ACTIVE_STATUS         = 'payment/tig_buckaroo_emandate/active_status';
    const XPATH_EMANDATE_ORDER_STATUS_SUCCESS  = 'payment/tig_buckaroo_emandate/order_status_success';
    const XPATH_EMANDATE_ORDER_STATUS_FAILED   = 'payment/tig_buckaroo_emandate/order_status_failed';
    const XPATH_EMANDATE_ORDER_EMAIL           = 'payment/tig_buckaroo_emandate/order_email';
    const XPATH_EMANDATE_AVAILABLE_IN_BACKEND  = 'payment/tig_buckaroo_emandate/available_in_backend';

    const XPATH_EMANDATE_SEQUENCE_TYPE         = 'payment/tig_buckaroo_emandate/sequence_type';
    const XPATH_EMANDATE_REASON                = 'payment/tig_buckaroo_emandate/reason';
    const XPATH_EMANDATE_LANGUAGE              = 'payment/tig_buckaroo_emandate/language';

    const XPATH_ALLOWED_CURRENCIES             = 'payment/tig_buckaroo_emandate/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                 = 'payment/tig_buckaroo_emandate/allowspecific';
    const XPATH_SPECIFIC_COUNTRY               = 'payment/tig_buckaroo_emandate/specificcountry';

    /**
     * @var array
     */
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
    ];

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_EMANDATE_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Emandate::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'emandate' => [
                        'banks' => $issuers,
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
            self::XPATH_EMANDATE_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
