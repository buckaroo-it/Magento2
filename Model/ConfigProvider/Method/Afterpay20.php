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
use Buckaroo\Magento2\Model\Method\Afterpay20 as Afterpay20Method;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Afterpay20 extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_afterpay20/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_afterpay20/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_afterpay20/specificcountry';

    const XPATH_AFTERPAY20_ACTIVE                 = 'payment/buckaroo_magento2_afterpay20/active';
    const XPATH_AFTERPAY20_SUBTEXT                = 'payment/buckaroo_magento2_afterpay20/subtext';
    const XPATH_AFTERPAY20_SUBTEXT_STYLE          = 'payment/buckaroo_magento2_afterpay20/subtext_style';
    const XPATH_AFTERPAY20_SUBTEXT_COLOR          = 'payment/buckaroo_magento2_afterpay20/subtext_color';
    const XPATH_AFTERPAY20_PAYMENT_FEE            = 'payment/buckaroo_magento2_afterpay20/payment_fee';
    const XPATH_AFTERPAY20_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_afterpay20/payment_fee_label';
    const XPATH_AFTERPAY20_SEND_EMAIL             = 'payment/buckaroo_magento2_afterpay20/send_email';
    const XPATH_AFTERPAY20_ACTIVE_STATUS          = 'payment/buckaroo_magento2_afterpay20/active_status';
    const XPATH_AFTERPAY20_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_afterpay20/order_status_success';
    const XPATH_AFTERPAY20_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_afterpay20/order_status_failed';
    const XPATH_AFTERPAY20_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_afterpay20/available_in_backend';
    const XPATH_AFTERPAY20_DUE_DATE               = 'payment/buckaroo_magento2_afterpay20/due_date';
    const XPATH_AFTERPAY20_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_afterpay20/allowed_currencies';
    const XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP =
        'payment/buckaroo_magento2_afterpay20/create_invoice_after_shipment';
    
    const XPATH_AFTERPAY20_CUSTOMER_TYPE          = 'payment/buckaroo_magento2_afterpay20/customer_type';
    const XPATH_AFTERPAY20_MIN_AMOUNT_B2B         = 'payment/buckaroo_magento2_afterpay20/min_amount_b2b';
    const XPATH_AFTERPAY20_MAX_AMOUNT_B2B         = 'payment/buckaroo_magento2_afterpay20/max_amount_b2b';
    

    const XPATH_SPECIFIC_CUSTOMER_GROUP           = 'payment/buckaroo_magento2_afterpay20/specificcustomergroup';
    const XPATH_FINANCIAL_WARNING                = 'payment/buckaroo_magento2_afterpay20/financial_warning';

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
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'is_b2b'     => $this->getCustomerType() !== AfterpayCustomerType::CUSTOMER_TYPE_B2C,
                        'showFinancialWarning' => $this->scopeConfig->getValue(
                            self::XPATH_FINANCIAL_WARNING,
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                        ) !== "0"
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
