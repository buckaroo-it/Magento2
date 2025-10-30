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
     * @param  Observer  $observer
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

    private function removeAllItems($quote): void
    {
        $items = $quote->getItemsCollection();
        foreach ($items as $item) {
            $quote->removeItem($item->getId());
        }
    }
}
