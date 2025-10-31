<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

use Buckaroo\Magento2\Exception;

class SepaDirectDebit extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_sepadirectdebit';

    public const XPATH_SEPADIRECTDEBIT_ACTIVE_STATUS_CM3           = 'active_status_cm3';
    public const XPATH_SEPADIRECTDEBIT_SCHEME_KEY                  = 'scheme_key';
    public const XPATH_SEPADIRECTDEBIT_MAX_STEP_INDEX              = 'max_step_index';
    public const XPATH_SEPADIRECTDEBIT_CM3_DUE_DATE                = 'cm3_due_date';
    public const XPATH_SEPADIRECTDEBIT_PAYMENT_METHOD_AFTER_EXPIRY = 'payment_method_after_expiry';
    public const XPATH_SEPADIRECTDEBIT_PAYMENT_FEE = 'payment/buckaroo_magento2_sepadirectdebit/payment_fee';


    /**
     * Check if Credit Management is enabled
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SEPADIRECTDEBIT_ACTIVE_STATUS_CM3, $store);
    }

    /**
     * Credit Management Scheme Key
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getSchemeKey($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SEPADIRECTDEBIT_SCHEME_KEY, $store);
    }

    /**
     * Get Max level of the Credit Management steps
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SEPADIRECTDEBIT_MAX_STEP_INDEX, $store);
    }

    /**
     * Get credit managment due date, amount of days after the order date
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCm3DueDate($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SEPADIRECTDEBIT_CM3_DUE_DATE, $store);
    }

    /**
     * Get payment method which can be used after the payment due date.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SEPADIRECTDEBIT_PAYMENT_METHOD_AFTER_EXPIRY, $store);
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_SEPADIRECTDEBIT_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
