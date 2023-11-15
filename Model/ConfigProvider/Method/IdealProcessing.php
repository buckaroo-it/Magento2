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
    const XPATH_IDEALPROCESSING_SUBTEXT              = 'payment/buckaroo_magento2_idealprocessing/subtext';
    const XPATH_IDEALPROCESSING_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_idealprocessing/subtext_style';
    const XPATH_IDEALPROCESSING_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_idealprocessing/subtext_color';
    const XPATH_IDEALPROCESSING_ACTIVE_STATUS        = 'payment/buckaroo_magento2_idealprocessing/active_status';
    const XPATH_IDEALPROCESSING_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_idealprocessing/order_status_success';
    const XPATH_IDEALPROCESSING_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_idealprocessing/order_status_failed';
    const XPATH_IDEALPROCESSING_ORDER_EMAIL          = 'payment/buckaroo_magento2_idealprocessing/order_email';
    const XPATH_IDEALPROCESSING_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_idealprocessing/available_in_backend';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_idealprocessing/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC     = 'payment/buckaroo_magento2_idealprocessing/allowspecific';
    const XPATH_SPECIFIC_COUNTRY   = 'payment/buckaroo_magento2_idealprocessing/specificcountry';
    const XPATH_SELECTION_TYPE     = 'buckaroo_magento2/account/selection_type';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_idealprocessing/specificcustomergroup';
    const XPATH_SHOW_ISSUERS            = 'payment/buckaroo_magento2_idealprocessing/show_issuers';

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
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                        'showIssuers' => $this->canShowIssuers()
                    ],
                ],
            ],
        ];
    }

    /**
     * Can show issuer selection in checkout
     *
     * @param string|null $storeId
     *
     * @return boolean
     */
    public function canShowIssuers(string $storeId = null): bool {
        return $this->scopeConfig->getValue(
            self::XPATH_SHOW_ISSUERS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) == 1;
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
