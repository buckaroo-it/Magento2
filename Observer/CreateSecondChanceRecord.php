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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

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
     * @param \Buckaroo\Magento2\Model\SecondChanceRepository      $secondChanceRepository
     * @param \Buckaroo\Magento2\Model\ConfigProvider\SecondChance $configProvider
     * @param \Buckaroo\Magento2\Logging\Log                       $logging
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
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$this->shouldCreateSecondChanceRecord($order)) {
            return;
        }

        try {
            if ($this->recordExists($order->getIncrementId())) {
                return;
            }

            $this->secondChanceRepository->createSecondChance($order);
            $this->logging->addDebug('SecondChance record created for order: ' . $order->getIncrementId());

        } catch (\Exception $e) {
            $this->logging->addError(
                'Error creating SecondChance record for order ' .
                $order->getIncrementId() . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if SecondChance record should be created for this order
     *
     * @param Order|null $order
     * @return bool
     */
    private function shouldCreateSecondChanceRecord($order): bool
    {
        if (!$order || !$order->getId()) {
            return false;
        }

        if (!in_array($order->getState(), ['pending_payment', 'canceled'])) {
            return false;
        }

        if (!$this->configProvider->isSecondChanceEnabled($order->getStore())) {
            return false;
        }

        $payment = $order->getPayment();
        if (!$payment || strpos($payment->getMethod(), 'buckaroo') === false) {
            return false;
        }

        if ($payment->getMethod() === Transfer::CODE) {
            return false;
        }

        return true;
    }

    /**
     * Check if SecondChance record already exists for this order
     *
     * @param string $orderId
     * @return bool
     * @throws LocalizedException
     */
    private function recordExists(string $orderId): bool
    {
        try {
            $this->secondChanceRepository->getByOrderId($orderId);
            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
    }
}
