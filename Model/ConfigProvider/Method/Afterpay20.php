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

use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Afterpay20 extends AbstractConfigProvider
{
    public const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_afterpay20/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_afterpay20/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_afterpay20/specificcountry';

    public const XPATH_AFTERPAY20_ACTIVE                 = 'payment/buckaroo_magento2_afterpay20/active';
    public const XPATH_AFTERPAY20_SUBTEXT                = 'payment/buckaroo_magento2_afterpay20/subtext';
    public const XPATH_AFTERPAY20_SUBTEXT_STYLE          = 'payment/buckaroo_magento2_afterpay20/subtext_style';
    public const XPATH_AFTERPAY20_SUBTEXT_COLOR          = 'payment/buckaroo_magento2_afterpay20/subtext_color';
    public const XPATH_AFTERPAY20_PAYMENT_FEE            = 'payment/buckaroo_magento2_afterpay20/payment_fee';
    public const XPATH_AFTERPAY20_SEND_EMAIL             = 'payment/buckaroo_magento2_afterpay20/send_email';
    public const XPATH_AFTERPAY20_ACTIVE_STATUS          = 'payment/buckaroo_magento2_afterpay20/active_status';
    public const XPATH_AFTERPAY20_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_afterpay20/order_status_success';
    public const XPATH_AFTERPAY20_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_afterpay20/order_status_failed';
    public const XPATH_AFTERPAY20_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_afterpay20/available_in_backend';
    public const XPATH_AFTERPAY20_DUE_DATE               = 'payment/buckaroo_magento2_afterpay20/due_date';
    public const XPATH_AFTERPAY20_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_afterpay20/allowed_currencies';
    public const XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP =
        'payment/buckaroo_magento2_afterpay20/create_invoice_after_shipment';

    public const XPATH_AFTERPAY20_CUSTOMER_TYPE          = 'payment/buckaroo_magento2_afterpay20/customer_type';
    public const XPATH_AFTERPAY20_MIN_AMOUNT_B2B         = 'payment/buckaroo_magento2_afterpay20/min_amount_b2b';
    public const XPATH_AFTERPAY20_MAX_AMOUNT_B2B         = 'payment/buckaroo_magento2_afterpay20/max_amount_b2b';


    public const XPATH_SPECIFIC_CUSTOMER_GROUP           = 'payment/buckaroo_magento2_afterpay20/specificcustomergroup';

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

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel();

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay20' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'is_b2b'     => $this->getCustomerType() !== AfterpayCustomerType::CUSTOMER_TYPE_B2C,
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

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getCreateInvoiceAfterShipment($storeId = null)
    {
        $createInvoiceAfterShipment = $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $createInvoiceAfterShipment ? $createInvoiceAfterShipment : false;
    }

    /**
     * Get customer type
     *
     * @param null|int $storeId
     *
     * @return string
     */
    public function getCustomerType($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_AFTERPAY20_CUSTOMER_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
