<?php

namespace Buckaroo\Magento2\Service;

use Magento\Reward\Model\RewardFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order;

if (class_exists(Magento\Reward\Model\RewardFactory::class)) {
    class RewardPoints
    {

        protected RewardFactory $rewardFactory;

        protected StoreManagerInterface $storeManager;

        public function __construct(
            RewardFactory $rewardFactory,
            StoreManagerInterface $storeManager
        ) {
            $this->rewardFactory = $rewardFactory;
            $this->storeManager = $storeManager;
        }
    
        public function returnRewardPoints(Order $order): void
        {
            if (
                $order->getRewardPointsBalance() > 0 &&
                $order->getCustomerId() !== null
            ) {
    
                $this->rewardFactory->create()->setCustomerId(
                    $order->getCustomerId()
                )->setWebsiteId(
                    $this->storeManager->getStore($order->getStoreId())->getWebsiteId()
                )->setAction(
                    \Magento\Reward\Model\Reward::REWARD_ACTION_REVERT
                )->setPointsDelta(
                    $order->getRewardPointsBalance()
                )->setActionEntity(
                    $order
                )->updateRewardPoints();
            }
        }
    }
} else {
    class RewardPoints
    {
        public function returnRewardPoints(Order $order): void
        {
            //if reward points module is not enabled
        }
    }
}