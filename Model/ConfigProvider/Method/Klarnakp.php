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

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Klarnakp extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES            = 'buckaroo/buckaroo_magento2_klarnakp/allowed_currencies';
    const XPATH_ALLOW_SPECIFIC                = 'payment/buckaroo_magento2_klarnakp/allowspecific';
    const XPATH_SPECIFIC_COUNTRY              = 'payment/buckaroo_magento2_klarnakp/specificcountry';
    const XPATH_KLARNAKP_ACTIVE                 = 'payment/buckaroo_magento2_klarnakp/active';
    const XPATH_KLARNAKP_PAYMENT_FEE            = 'payment/buckaroo_magento2_klarnakp/payment_fee';
    const XPATH_KLARNAKP_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_klarnakp/payment_fee_label';
    const XPATH_KLARNAKP_SEND_EMAIL             = 'payment/buckaroo_magento2_klarnakp/send_email';
    const XPATH_KLARNAKP_ACTIVE_STATUS          = 'payment/buckaroo_magento2_klarnakp/active_status';
    const XPATH_KLARNAKP_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_klarnakp/order_status_success';
    const XPATH_KLARNAKP_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_klarnakp/order_status_failed';
    const XPATH_KLARNAKP_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_klarnakp/available_in_backend';
    const XPATH_KLARNAKP_DUE_DATE               = 'payment/buckaroo_magento2_klarnakp/due_date';
    const XPATH_KLARNAKP_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_klarnakp/allowed_currencies';
    const XPATH_KLARNAKP_BUSINESS               = 'payment/buckaroo_magento2_klarnakp/business';
    const XPATH_KLARNAKP_PAYMENT_METHODS        = 'payment/buckaroo_magento2_klarnakp/payment_method';
    const XPATH_KLARNAKP_HIGH_TAX               = 'payment/buckaroo_magento2_klarnakp/high_tax';
    const XPATH_KLARNAKP_MIDDLE_TAX             = 'payment/buckaroo_magento2_klarnakp/middle_tax';
    const XPATH_KLARNAKP_LOW_TAX                = 'payment/buckaroo_magento2_klarnakp/low_tax';
    const XPATH_KLARNAKP_ZERO_TAX               = 'payment/buckaroo_magento2_klarnakp/zero_tax';
    const XPATH_KLARNAKP_NO_TAX                 = 'payment/buckaroo_magento2_klarnakp/no_tax';
    const XPATH_KLARNAKP_GET_INVOICE            = 'payment/buckaroo_magento2_klarnakp/send_invoice';
    const XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP = 'payment/buckaroo_magento2_klarnakp/create_invoice_after_shipment';

    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_KLARNAKP_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(\Buckaroo\Magento2\Model\Method\Klarnakp::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'klarnakp' => [
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
            self::XPATH_KLARNAKP_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : 0;
    }

    public function getInvoiceSendMethod($storeId = null)
    {
        return $this->getConfigFromXpath(static::XPATH_KLARNAKP_GET_INVOICE, $storeId);
    }

    /**
     * @param null|int $storeId
     *
     * @return bool
     */
    public function getEnabled($storeId = null)
    {
        $enabled = $this->scopeConfig->getValue(
            self::XPATH_KLARNAKP_ACTIVE,
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
            self::XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $createInvoiceAfterShipment ? $createInvoiceAfterShipment : false;
    }
}
