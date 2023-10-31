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
use Buckaroo\Magento2\Model\Method\Capayable\Installments as CapayableIn3Method;

/**
 * @method getPaymentFeeLabel()
 */
class CapayableIn3 extends AbstractConfigProvider
{
    const XPATH_CAPAYABLEIN3_PAYMENT_FEE          = 'payment/buckaroo_magento2_capayablein3/payment_fee';
    const XPATH_CAPAYABLEIN3_PAYMENT_FEE_LABEL    = 'payment/buckaroo_magento2_capayablein3/payment_fee_label';
    const XPATH_CAPAYABLEIN3_ACTIVE               = 'payment/buckaroo_magento2_capayablein3/active';
    const XPATH_CAPAYABLEIN3_SUBTEXT              = 'payment/buckaroo_magento2_capayablein3/subtext';
    const XPATH_CAPAYABLEIN3_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_capayablein3/subtext_style';
    const XPATH_CAPAYABLEIN3_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_capayablein3/subtext_color';
    const XPATH_CAPAYABLEIN3_ACTIVE_STATUS        = 'payment/buckaroo_magento2_capayablein3/active_status';
    const XPATH_CAPAYABLEIN3_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_capayablein3/order_status_success';
    const XPATH_CAPAYABLEIN3_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_capayablein3/order_status_failed';
    const XPATH_CAPAYABLEIN3_ORDER_EMAIL          = 'payment/buckaroo_magento2_capayablein3/order_email';
    const XPATH_CAPAYABLEIN3_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_capayablein3/available_in_backend';

    const XPATH_CAPAYABLEIN3_API_VERSION = 'payment/buckaroo_magento2_capayablein3/api_version';
    const XPATH_CAPAYABLEIN3_PAYMENT_LOGO = 'payment/buckaroo_magento2_capayablein3/payment_logo';


    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_capayablein3/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/buckaroo_magento2_capayablein3/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/buckaroo_magento2_capayablein3/specificcountry';

    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_capayablein3/specificcustomergroup';

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
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'logo' => $this->getLogo()
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

    public function isV2($storeId = null): bool
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_API_VERSION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === 'V2';
    }

    public function getLogo($storeId = null): string
    {
        $logo = $this->scopeConfig->getValue(
            self::XPATH_CAPAYABLEIN3_PAYMENT_LOGO,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($this->isV2($storeId)) {
            return 'in3.svg';
        }

        if (!is_string($logo)) {
            return 'in3-ideal.svg';
        }

        return $logo;
    }
}
