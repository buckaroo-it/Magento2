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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use \Magento\Store\Model\ScopeInterface;

/**
 * @method mixed getEnabled()
 * @method mixed getAllowPush()
 */
class Refund extends AbstractConfigProvider
{

    /**
     * XPATHs to configuration values for buckaroo_magento2_predefined
     */
    const XPATH_REFUND_ENABLED      = 'buckaroo_magento2/refund/enabled';
    const XPATH_REFUND_ALLOW_PUSH   = 'buckaroo_magento2/refund/allow_push';

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'enabled' => $this->getEnabled($store),
            'allow_push' => $this->getAllowPush($store),
        ];
        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnabled($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_REFUND_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowPush($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_REFUND_ALLOW_PUSH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
