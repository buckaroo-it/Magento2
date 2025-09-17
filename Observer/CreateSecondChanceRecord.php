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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CreateSecondChanceRecord implements ObserverInterface
{
    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\SecondChance
     */
    protected $configProvider;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logging;

    /**
     * @param \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository
     * @param \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider
     * @param \Buckaroo\Magento2\Logging\Log $logging
     */
    public function __construct(
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository,
        \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider,
        \Buckaroo\Magento2\Logging\Log $logging
    ) {
        $this->secondChanceRepository = $secondChanceRepository;
        $this->configProvider = $configProvider;
        $this->logging = $logging;
    }

    /**
     * Create SecondChance record when order is saved with failed payment status
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        
        if (!$order || !$order->getId()) {
            return;
        }

        // Only create for orders with failed/pending payment states
        if (!in_array($order->getState(), ['pending_payment', 'canceled'])) {
            return;
        }

        // Check if SecondChance is enabled for this store
        if (!$this->configProvider->isSecondChanceEnabled($order->getStore())) {
            return;
        }

        // Check if this is a Buckaroo payment method
        $payment = $order->getPayment();
        if (!$payment || strpos($payment->getMethod(), 'buckaroo') === false) {
            return;
        }

        try {
            // Check if SecondChance record already exists for this order
            try {
                $this->secondChanceRepository->getByOrderId($order->getIncrementId());
                // Record already exists, don't create duplicate
                return;
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                // Record doesn't exist, proceed with creation
            }

            $this->secondChanceRepository->createSecondChance($order);
            $this->logging->addDebug('SecondChance record created for order: ' . $order->getIncrementId());

        } catch (\Exception $e) {
            $this->logging->addError('Error creating SecondChance record for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }
}
