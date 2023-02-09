<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class SaveOrderBeforeDataBuilder implements BuilderInterface
{
    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @param Account $configProviderAccount
     */
    public function __construct(Account $configProviderAccount)
    {
        $this->configProviderAccount = $configProviderAccount;
    }

    /**
     * Save Order Before Request
     *
     * @param array $buildSubject
     * @return array|void
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /** @var Order $order */
        $order = $paymentDO->getOrder()->getOrder();
        $store = $order->getStoreId();

        if ($this->configProviderAccount->getCreateOrderBeforeTransaction($store)) {
            $newStatus = $this->configProviderAccount->getOrderStatusNew($store);
            $orderState = 'new';
            if (!$newStatus) {
                $newStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }

            $order->setState($orderState);
            $order->setStatus($newStatus);
            $order->save();
        }

        return [];
    }
}
