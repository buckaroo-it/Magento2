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
use Magento\Store\Model\ScopeInterface;

class Transfer extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_transfer';

    public const XPATH_TRANSFER_DUE_DATE = 'payment/buckaroo_magento2_transfer/due_date';

    public const XPATH_TRANSFER_ACTIVE_STATUS_CM3 = 'payment/buckaroo_magento2_transfer/active_status_cm3';
    public const XPATH_TRANSFER_SCHEME_KEY = 'payment/buckaroo_magento2_transfer/scheme_key';
    public const XPATH_TRANSFER_MAX_STEP_INDEX = 'payment/buckaroo_magento2_transfer/max_step_index';
    public const XPATH_TRANSFER_CM3_DUE_DATE = 'payment/buckaroo_magento2_transfer/cm3_due_date';
    public const XPATH_TRANSFER_PAYMENT_METHOD_AFTER_EXPIRY =
        'payment/buckaroo_magento2_transfer/payment_method_after_expiry';

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

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'transfer' => [
                        'sendEmail'         => (bool)$this->getOrderEmail(),
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ]
                ]
            ]
        ];
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
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_DUE_DATE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if Credit Management is enabled
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getActiveStatusCm3($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_ACTIVE_STATUS_CM3,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Credit Management Scheme Key
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSchemeKey($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_SCHEME_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Max level of the Credit Management steps
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMaxStepIndex($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_MAX_STEP_INDEX,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get credit managment due date, amount of days after the order date
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getCm3DueDate($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_CM3_DUE_DATE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get payment method which can be used after the payment due date.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_TRANSFER_PAYMENT_METHOD_AFTER_EXPIRY,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
