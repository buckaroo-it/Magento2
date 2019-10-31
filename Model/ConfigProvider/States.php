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

namespace TIG\Buckaroo\Model\ConfigProvider;

use \TIG\Buckaroo\Model\ConfigProvider;

/**
 * @method mixed getOrderStateNew()
 * @method mixed getOrderStateSuccess()
 * @method mixed getOrderStateFailed()
 * @method mixed getOrderStatePending()
 * @method mixed getOrderStateIncorrect()
 */
class States extends AbstractConfigProvider
{

    /**
     * XPATHs to configuration values for tig_buckaroo_predefined
     */
    const XPATH_STATES_ORDER_STATE_NEW          = 'tig_buckaroo/states/order_state_new';
    const XPATH_STATES_ORDER_STATE_SUCCESS      = 'tig_buckaroo/states/order_state_success';
    const XPATH_STATES_ORDER_STATE_FAILED       = 'tig_buckaroo/states/order_state_failed';
    const XPATH_STATES_ORDER_STATE_PENDING      = 'tig_buckaroo/states/order_state_pending';
    const XPATH_STATES_ORDER_STATE_INCORRECT    = 'tig_buckaroo/states/order_state_incorrect';

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'order_state_new'       => $this->getOrderStateNew($store),
            'order_state_pending'   => $this->getOrderStatePending($store),
            'order_state_success'   => $this->getOrderStateSuccess($store),
            'order_state_failed'    => $this->getOrderStateFailed($store),
            'order_state_incorrect' => $this->getOrderStateIncorrect($store),
        ];
        return $config;
    }
}
