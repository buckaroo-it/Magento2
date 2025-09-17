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

class Transfer extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_transfer';

    public const XPATH_TRANSFER_DUE_DATE = 'due_date';

    public const XPATH_TRANSFER_ACTIVE_STATUS_CM3           = 'active_status_cm3';
    public const XPATH_TRANSFER_SCHEME_KEY                  = 'scheme_key';
    public const XPATH_TRANSFER_MAX_STEP_INDEX              = 'max_step_index';
    public const XPATH_TRANSFER_CM3_DUE_DATE                = 'cm3_due_date';
    public const XPATH_TRANSFER_PAYMENT_METHOD_AFTER_EXPIRY = 'payment_method_after_expiry';
    public const XPATH_TRANSFER_PAYMENT_FEE            = 'payment/buckaroo_magento2_transfer/payment_fee';


    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'sendEmail' => $this->hasOrderEmail(),
        ]);
    }

    /**
     * Get Due Date Y-m-d
     *
     * @param $store
     * @return string
     */
    public function getDueDateFormated($store = null): string
    {
        $dueDays = (float)$this->getDueDate($store);

        return (new \DateTime())
            ->modify("+{$dueDays} day")
            ->format('Y-m-d');
    }

    /**
     * Get due date until order will be cancelled, amount of days after the order date
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getDueDate($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_DUE_DATE, $store);
    }

    /**
     * Check if Credit Management is enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_ACTIVE_STATUS_CM3, $store);
    }

    /**
     * Credit Management Scheme Key
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSchemeKey($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_SCHEME_KEY, $store);
    }

    /**
     * Get Max level of the Credit Management steps
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_MAX_STEP_INDEX, $store);
    }

    /**
     * Get credit managment due date, amount of days after the order date
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getCm3DueDate($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_CM3_DUE_DATE, $store);
    }

    /**
     * Get payment method which can be used after the payment due date.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_TRANSFER_PAYMENT_METHOD_AFTER_EXPIRY, $store);
    }
    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_TRANSFER_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
