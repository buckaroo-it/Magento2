<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class SaveOrderBeforeDataBuilder implements BuilderInterface
{
    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param Account $configProviderAccount
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Account $configProviderAccount,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->configProviderAccount = $configProviderAccount;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Save Order Before Request.
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
            if (!$newStatus) {
                $newStatus = $order->getConfig()->getStateDefaultStatus('new');
            }

            $order->setStatus($newStatus);
            $this->orderRepository->save($order);
        }

        return [];
    }
}
