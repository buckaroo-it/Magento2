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

namespace Buckaroo\Magento2\Plugin;

use Magento\Quote\Model\Quote;
use Magento\Framework\Exception\CouldNotSaveException;

// @codingStandardsIgnoreStart
if (class_exists('\Mageplaza\Osc\Model\CheckoutManagement')) {

    class CheckoutManagement extends \Mageplaza\Osc\Model\CheckoutManagement
    {
        public function updateItemQty($cartId, $itemId, $itemQty)
        {
            /** @phpstan-ignore-next-line */
            $quote = $this->checkoutSession->getQuote();
            if ($this->getAlreadyPaid($this->checkoutSession->getQuote()) > 0) {
                throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
            }

            /** @phpstan-ignore-next-line */
            return parent::updateItemQty($cartId, $itemId, $itemQty);
        }

        public function removeItemById($cartId, $itemId)
        {
            /** @phpstan-ignore-next-line */
            if ($this->getAlreadyPaid($this->checkoutSession->getQuote()) > 0) {
                throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
            }
            /** @phpstan-ignore-next-line */
            return parent::removeItemById($cartId, $itemId);
        }

        /**
         * Get quote already payed amount
         *
         * @param Magento\Quote\Model\Quote $quote
         *
         * @return float
         */
        private function getAlreadyPaid(Quote $quote)
        {
            $groupTransaction = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Buckaroo\Magento2\Helper\PaymentGroupTransaction::class);

            return $groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
        }
    }

} else {
    class CheckoutManagement
    {
    }
}

// @codingStandardsIgnoreEnd
