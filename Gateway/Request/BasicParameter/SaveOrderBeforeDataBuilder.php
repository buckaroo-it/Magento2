<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Exception;
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
     *
     * @throws Exception
     *
     * @return array
     */
    public function build(array $buildSubject): array
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

            $order->setState(Order::STATE_NEW);
            $order->setStatus($newStatus);
            $order->save();
        }

        return [];
    }
}
