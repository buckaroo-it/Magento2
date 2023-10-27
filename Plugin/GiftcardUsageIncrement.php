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

namespace Buckaroo\Magento2\Plugin;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class GiftcardUsageIncrement
{
    /**
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * @param OrderFactory $orderFactory
     */
    public function __construct(OrderFactory $orderFactory)
    {
        $this->orderFactory = $orderFactory;
    }

    /**
     * Return the same order created when we applied a giftcard
     *
     * @param QuoteManagement $subject
     * @param \Closure $proceed
     * @param Quote $quote
     * @param array $orderData
     * @return OrderInterface|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws \Throwable
     */
    public function aroundSubmit(QuoteManagement $subject, \Closure $proceed, Quote $quote, $orderData = [])
    {
        if ($this->isGroupTransaction($quote)) {
            $order = $this->getOrderByIncrementId($quote->getReservedOrderId());
            if ($order->getId()) {
//                $quote->setOrigOrderId($order->getId());
            }
        }

        try {
            return $proceed($quote, $orderData);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Load and return order by its increment ID
     *
     * @param string $incrementId
     * @return Order
     */
    protected function getOrderByIncrementId(string $incrementId): Order
    {
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * Check if is group transaction and order was already created
     *
     * @param Quote $quote
     * @return bool
     */
    private function isGroupTransaction(Quote $quote): bool
    {
        return $quote->getReservedOrderId() && $quote->getBaseBuckarooAlreadyPaid() > 0;
    }
}