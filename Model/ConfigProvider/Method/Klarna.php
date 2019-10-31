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


/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Klarna extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES            = 'buckaroo/tig_buckaroo_klarna/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC                = 'payment/tig_buckaroo_klarna/allowspecific';
    const XPATH_SPECIFIC_COUNTRY              = 'payment/tig_buckaroo_klarna/specificcountry';
    const XPATH_KLARNA_ACTIVE                 = 'payment/tig_buckaroo_klarna/active';
    const XPATH_KLARNA_PAYMENT_FEE            = 'payment/tig_buckaroo_klarna/payment_fee';
    const XPATH_KLARNA_PAYMENT_FEE_LABEL      = 'payment/tig_buckaroo_klarna/payment_fee_label';
    const XPATH_KLARNA_SEND_EMAIL             = 'payment/tig_buckaroo_klarna/send_email';
    const XPATH_KLARNA_ACTIVE_STATUS          = 'payment/tig_buckaroo_klarna/active_status';
    const XPATH_KLARNA_ORDER_STATUS_SUCCESS   = 'payment/tig_buckaroo_klarna/order_status_success';
    const XPATH_KLARNA_ORDER_STATUS_FAILED    = 'payment/tig_buckaroo_klarna/order_status_failed';
    const XPATH_KLARNA_AVAILABLE_IN_BACKEND   = 'payment/tig_buckaroo_klarna/available_in_backend';
    const XPATH_KLARNA_DUE_DATE               = 'payment/tig_buckaroo_klarna/due_date';
    const XPATH_KLARNA_ALLOWED_CURRENCIES     = 'payment/tig_buckaroo_klarna/allowed_currencies';
    const XPATH_KLARNA_BUSINESS               = 'payment/tig_buckaroo_klarna/business';
    const XPATH_KLARNA_PAYMENT_METHODS        = 'payment/tig_buckaroo_klarna/payment_method';
    const XPATH_KLARNA_HIGH_TAX               = 'payment/tig_buckaroo_klarna/high_tax';
    const XPATH_KLARNA_MIDDLE_TAX             = 'payment/tig_buckaroo_klarna/middle_tax';
    const XPATH_KLARNA_LOW_TAX                = 'payment/tig_buckaroo_klarna/low_tax';
    const XPATH_KLARNA_ZERO_TAX               = 'payment/tig_buckaroo_klarna/zero_tax';
    const XPATH_KLARNA_NO_TAX                 = 'payment/tig_buckaroo_klarna/no_tax';
    const XPATH_KLARNA_GET_INVOICE            = 'payment/tig_buckaroo_klarna/send_invoice';
    const XPATH_KLARNA_CREATE_INVOICE_BY_SHIP = 'payment/tig_buckaroo_klarna/create_invoice_after_shipment';

    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_KLARNA_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Klarna::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarna' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
                        'paymentMethod'     => $this->getPaymentMethod(),
                        'paymentFee'        => $this->getPaymentFee(),
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
            self::XPATH_KLARNA_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : 0;
    }

    public function getInvoiceSendMethod($storeId = null)
    {
        return $this->getConfigFromXpath(static::XPATH_KLARNA_GET_INVOICE, $storeId);
    }

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getEnabled($storeId = null)
    {
        $enabled = $this->scopeConfig->getValue(
            self::XPATH_KLARNA_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $enabled ? $enabled : false;
    }

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getCreateInvoiceAfterShipment($storeId = null)
    {
        $createInvoiceAfterShipment = $this->scopeConfig->getValue(
            self::XPATH_KLARNA_CREATE_INVOICE_BY_SHIP,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $createInvoiceAfterShipment ? $createInvoiceAfterShipment : false;
    }
}