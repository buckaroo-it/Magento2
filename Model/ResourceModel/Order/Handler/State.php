<?php

namespace Buckaroo\Magento2\Model\ResourceModel\Order\Handler;

use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;

class State extends \Magento\Sales\Model\ResourceModel\Order\Handler\State
{
    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    public function __construct(
        Factory $configProviderMethodFactory,
        Log $logging
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->logging = $logging;
    }

    public function check(Order $order)
    {
        if ($order->getPayment() &&
            $order->getPayment()->getMethodInstance()->getCode() == 'buckaroo_magento2_payperemail'
        ) {
            $config = $this->configProviderMethodFactory->get(
                \Buckaroo\Magento2\Model\ConfigProvider\Method\Payperemail::CODE
            );
            if ($config->getEnabledB2B()) {
                if ($order->getState() == Order::STATE_PROCESSING) {
                    if ($order->getInvoiceCollection() && $order->getInvoiceCollection()->getFirstItem()) {
                        if ($order->getInvoiceCollection()->getFirstItem()->getState() == 1) {
                            $this->logging->addDebug(__METHOD__ . '|10|');
                            return $this;
                        }
                    }
                }
            }
        }

        return parent::check($order);
    }
}
