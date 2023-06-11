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
    protected Account $configProviderAccount;

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
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /** @var Order $order */
        $order = $paymentDO->getOrder()->getOrder();
        $store = $order->getStoreId();

        if ($this->configProviderAccount->getCreateOrderBeforeTransaction($store)) {
            $newStatus = $this->configProviderAccount->getOrderStatusNew($store);
            $orderState = 'new';
            if (!$newStatus) {
                $newStatus = $order->getConfig()->getStateDefaultStatus('new');
            }

            $order->setState($orderState);
            $order->setStatus($newStatus);
            $order->save();
        }

        return [];
    }
}
