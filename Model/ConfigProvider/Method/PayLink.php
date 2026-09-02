<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Magento\Framework\App\Area;
use Magento\Store\Model\ScopeInterface;

class PayLink extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paylink';

    public const XPATH_PAYLINK_PAYMENT_METHOD = 'payment_method';
    public const XPATH_PAYLINK_PAYMENT_FEE          = 'payment/buckaroo_magento2_paylink/payment_fee';

    /**
     * Payment method is visible for area code
     *
     * @param string $areaCode
     *
     * @return bool
     */
    public function isVisibleForAreaCode(string $areaCode): bool
    {
        return $areaCode === Area::AREA_ADMINHTML;
    }

    /**
     * Get payment method from paylink paymennt methods list
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getPaymentMethod($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYLINK_PAYMENT_METHOD, $store);
    }

    /**
     * Can send mail by email
     *
     * @param null|int $storeId
     *
     * @return bool
     */
    public function hasSendMail($storeId = null): bool
    {
        $sendMail = $this->scopeConfig->getValue(
            PayPerEmail::XPATH_PAYPEREMAIL_SEND_MAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (bool)$sendMail;
    }

    /**
     * Get the PayLink payment fee.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_PAYLINK_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
