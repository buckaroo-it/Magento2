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

class IdealGateway extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_idealgateway';
    protected $methodCode = self::CODE;

    public const XPATH_IDEAL_PAYMENT_FEE           = 'payment/' . self::CODE . '/payment_fee';
    public const XPATH_IDEAL_PAYMENT_FEE_LABEL     = 'payment/' . self::CODE . '/payment_fee_label';
    public const XPATH_IDEAL_ACTIVE                = 'payment/' . self::CODE . '/active';
    public const XPATH_IDEAL_ACTIVE_STATUS         = 'payment/' . self::CODE . '/active_status';
    public const XPATH_IDEAL_ORDER_STATUS_SUCCESS  = 'payment/' . self::CODE . '/order_status_success';
    public const XPATH_IDEAL_ORDER_STATUS_FAILED   = 'payment/' . self::CODE . '/order_status_failed';
    public const XPATH_IDEAL_ORDER_EMAIL           = 'payment/' . self::CODE . '/order_email';
    public const XPATH_IDEAL_AVAILABLE_IN_BACKEND  = 'payment/' . self::CODE . '/available_in_backend';
    public const XPATH_ALLOWED_CURRENCIES          = 'payment/' . self::CODE . '/allowed_currencies';
    public const XPATH_ALLOW_SPECIFIC              = 'payment/' . self::CODE . '/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY            = 'payment/' . self::CODE . '/specificcountry';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP     = 'payment/' . self::CODE . '/specificcustomergroup';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * Return checkout config
     *
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_IDEAL_ACTIVE,
            ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            self::CODE
        );

        $selectionType = $this->scopeConfig->getValue(
            \Buckaroo\Magento2\Model\ConfigProvider\Account::XPATH_ACCOUNT_SELECTION_TYPE,
            ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'idealgateway' => [
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
     * Return Payment Fee
     *
     * @param null|int $storeId
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_IDEAL_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: false;
    }
}
