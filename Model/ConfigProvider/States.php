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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Store\Model\ScopeInterface;

class States extends AbstractConfigProvider
{
    /**
     * XPATHs to configuration values for buckaroo_magento2_predefined
     */
    public const XPATH_STATES_ORDER_STATE_NEW       = 'buckaroo_magento2/states/order_state_new';
    public const XPATH_STATES_ORDER_STATE_SUCCESS   = 'buckaroo_magento2/states/order_state_success';
    public const XPATH_STATES_ORDER_STATE_FAILED    = 'buckaroo_magento2/states/order_state_failed';
    public const XPATH_STATES_ORDER_STATE_PENDING   = 'buckaroo_magento2/states/order_state_pending';
    public const XPATH_STATES_ORDER_STATE_INCORRECT = 'buckaroo_magento2/states/order_state_incorrect';

    /**
     * @inheritdoc
     */
    public function getConfig($store = null): array
    {
        return [
            'order_state_new'       => $this->getOrderStateNew($store),
            'order_state_pending'   => $this->getOrderStatePending($store),
            'order_state_success'   => $this->getOrderStateSuccess($store),
            'order_state_failed'    => $this->getOrderStateFailed($store),
            'order_state_incorrect' => $this->getOrderStateIncorrect($store),
        ];
    }

    /**
     * Get order state label for new order
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getOrderStateNew($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_STATES_ORDER_STATE_NEW,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get order state label for success order
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getOrderStateSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_STATES_ORDER_STATE_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get order state label for failed order
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getOrderStateFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_STATES_ORDER_STATE_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get order state label for pending order
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getOrderStatePending($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_STATES_ORDER_STATE_PENDING,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get order state label for incorrect order
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getOrderStateIncorrect($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_STATES_ORDER_STATE_INCORRECT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
