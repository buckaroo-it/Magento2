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

use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Model\Method\IdealProcessing as IdealProcessingMethod;

class IdealProcessing extends AbstractConfigProvider
{
    const XPATH_IDEALPROCESSING_PAYMENT_FEE          = 'payment/buckaroo_magento2_idealprocessing/payment_fee';
    const XPATH_IDEALPROCESSING_PAYMENT_FEE_LABEL    = 'payment/buckaroo_magento2_idealprocessing/payment_fee_label';
    const XPATH_IDEALPROCESSING_ACTIVE               = 'payment/buckaroo_magento2_idealprocessing/active';
    const XPATH_IDEALPROCESSING_ACTIVE_STATUS        = 'payment/buckaroo_magento2_idealprocessing/active_status';
    const XPATH_IDEALPROCESSING_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_idealprocessing/order_status_success';
    const XPATH_IDEALPROCESSING_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_idealprocessing/order_status_failed';
    const XPATH_IDEALPROCESSING_ORDER_EMAIL          = 'payment/buckaroo_magento2_idealprocessing/order_email';
    const XPATH_IDEALPROCESSING_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_idealprocessing/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_idealprocessing/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/buckaroo_magento2_idealprocessing/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/buckaroo_magento2_idealprocessing/specificcountry';
    const XPATH_SELECTION_TYPE     = 'buckaroo_magento2/account/selection_type';

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
            'name' => 'Bunq Bank',
            'code' => 'BUNQNL2A',
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
            'name' => 'Moneyou',
            'code' => 'MOYONL21',
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
        [
            'name' => 'Van Lanschot',
            'code' => 'FVLBNL22',
        ],
        [
            'name' => 'Handelsbanken',
            'code' => 'HANDNL2A',
        ],
    ];

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        if (!$this->scopeConfig->getValue(static::XPATH_IDEALPROCESSING_ACTIVE, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(IdealProcessingMethod::PAYMENT_METHOD_CODE);

        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'idealprocessing' => [
                        'banks' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_IDEALPROCESSING_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }
}
