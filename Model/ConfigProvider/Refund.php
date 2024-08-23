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

use \Buckaroo\Magento2\Model\ConfigProvider;
use Magento\Store\Model\ScopeInterface;

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

    public const XPATH_REFUND_PENDING_APPROVAL = 'buckaroo_magento2/refund/pending_approval';

    public const PENDING_REFUND_ON_APPROVE = 1;
    public const PENDING_REFUND_ON_REQUEST = 0;

    public const ADDITIONAL_INFO_PENDING_REFUND = 'pending_refund';


    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'enabled' => $this->getEnabled($store),
            'allow_push' => $this->getAllowPush($store),
            'pending_approval' => $this->getPendingApprovalSetting($store),
        ];
        return $config;
    }

    /**
     * Get the setting for creating a refund on approval for pending approval refunds.
     *
     * @param int|string|null $store
     * @return mixed
     */
    public function getPendingApprovalSetting($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_REFUND_PENDING_APPROVAL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
