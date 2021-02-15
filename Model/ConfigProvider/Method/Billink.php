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

use Buckaroo\Magento2\Model\Method\Billink as BillinkMethod;

use Buckaroo\Magento2\Model\Config\Source\BillinkBusiness;

/**
 * @method getDueDate()
 * @method getSendEmail()
 */
class Billink extends AbstractConfigProvider
{
    const XPATH_ALLOWED_CURRENCIES               = 'buckaroo/buckaroo_magento2_billink/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                   = 'payment/buckaroo_magento2_billink/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                 = 'payment/buckaroo_magento2_billink/specificcountry';

    const XPATH_BILLINK_ACTIVE                 = 'payment/buckaroo_magento2_billink/active';
    const XPATH_BILLINK_PAYMENT_FEE            = 'payment/buckaroo_magento2_billink/payment_fee';
    const XPATH_BILLINK_PAYMENT_FEE_LABEL      = 'payment/buckaroo_magento2_billink/payment_fee_label';
    const XPATH_BILLINK_SEND_EMAIL             = 'payment/buckaroo_magento2_billink/send_email';
    const XPATH_BILLINK_ACTIVE_STATUS          = 'payment/buckaroo_magento2_billink/active_status';
    const XPATH_BILLINK_ORDER_STATUS_SUCCESS   = 'payment/buckaroo_magento2_billink/order_status_success';
    const XPATH_BILLINK_ORDER_STATUS_FAILED    = 'payment/buckaroo_magento2_billink/order_status_failed';
    const XPATH_BILLINK_AVAILABLE_IN_BACKEND   = 'payment/buckaroo_magento2_billink/available_in_backend';
    const XPATH_BILLINK_DUE_DATE               = 'payment/buckaroo_magento2_billink/due_date';
    const XPATH_BILLINK_ALLOWED_CURRENCIES     = 'payment/buckaroo_magento2_billink/allowed_currencies';
    const XPATH_BILLINK_BUSINESS               = 'payment/buckaroo_magento2_billink/business';

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_BILLINK_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(BillinkMethod::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'billink' => [
                        'sendEmail'         => (bool) $this->getSendEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'businessMethod'    => $this->getBusiness(),
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
            self::XPATH_BILLINK_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * businessMethod 1 = B2C
     * businessMethod 2 = B2B
     *
     * @return bool|int
     */
    public function getBusiness()
    {
        $business = (int) $this->scopeConfig->getValue(
            self::XPATH_BILLINK_BUSINESS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $business ? $business : false;
    }
}
