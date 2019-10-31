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
 * @method mixed getEnabled()
 * @method mixed getAllowPush()
 */
class Refund extends AbstractConfigProvider
{

    /**
     * XPATHs to configuration values for tig_buckaroo_predefined
     */
    const XPATH_REFUND_ENABLED      = 'tig_buckaroo/refund/enabled';
    const XPATH_REFUND_ALLOW_PUSH   = 'tig_buckaroo/refund/allow_push';

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
}
