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

namespace Buckaroo\Magento2\Model\ResourceModel\Order\Handler;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Sales\Model\Order;

class State extends \Magento\Sales\Model\ResourceModel\Order\Handler\State
{
    /**
     * @var Factory
     */
    public Factory $configProviderMethodFactory;

    /**
     * @var Log
     */
    private Log $logging;

    /**
     * State constructor
     *
     * @param Factory $configProviderMethodFactory
     * @param Log $logging
     */
    public function __construct(
        Factory $configProviderMethodFactory,
        Log $logging
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->logging = $logging;
    }

    public function check(Order $order): State
    {
        if ($order->getPayment() &&
            $order->getPayment()->getMethodInstance()->getCode() == 'buckaroo_magento2_payperemail'
        ) {
            $config = $this->configProviderMethodFactory->get(PayPerEmail::CODE);
            if ($config->isEnabledB2B()
                && $order->getState() == Order::STATE_PROCESSING
                && $order->getInvoiceCollection() && $order->getInvoiceCollection()->getFirstItem()
                && $order->getInvoiceCollection()->getFirstItem()->getState() == 1
            ) {
                $this->logging->addDebug(__METHOD__ . '|10|');
                return $this;
            }
        }

        return parent::check($order);
    }
}
