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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\Event\Observer;

class PreventMergeQuoteObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @param PaymentGroupTransaction $groupTransaction
     */
    public function __construct(
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Prevent quote merging for group transactions by clearing the source quote items.
     *
     * @param Observer $observer
     * @return void
     *
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();

        $isGroupTransaction = $this->groupTransaction->isGroupTransaction($quote->getReservedOrderId());

        $sourceQuote = $observer->getEvent()->getData('source');

        if ($isGroupTransaction) {
            $this->removeAllItems($sourceQuote);
        }
    }

    /**
     * Remove all items from the given quote.
     *
     * @param mixed $quote
     * @return void
     */
    private function removeAllItems($quote): void
    {
        $items = $quote->getItemsCollection();
        foreach ($items as $item) {
            $quote->removeItem($item->getId());
        }
    }
}
