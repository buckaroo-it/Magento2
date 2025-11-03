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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;

class SetExistingOrderItems implements ObserverInterface
{
    /**
     * @var OrderItemCollectionFactory
     */
    protected $orderItemCollectionFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    public function __construct(
        OrderItemCollectionFactory $orderItemCollectionFactory,
        PaymentGroupTransaction $groupTransaction,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->groupTransaction = $groupTransaction;
        $this->logger = $logger;
    }

    /**
     * Set Buckaroo fee on sales_model_service_quote_submit_before event
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();

        if ($order && $order->getId()) {
            if ($this->groupTransaction->isGroupTransaction($order->getIncrementId())) {
                try {
                    $orderItems = $this->getOrderItemsByOrderId($order->getId());
                    $order->setItems($orderItems);
                } catch (\Exception $e) {
                    $this->logger->critical($e->getMessage());
                }
            }
        }
    }

    protected function getOrderItemsByOrderId($orderId)
    {
        $collection = $this->orderItemCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        return $collection->getItems();
    }
}
