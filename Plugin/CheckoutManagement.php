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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Quote\Model\Quote;
use Magento\Framework\Exception\CouldNotSaveException;

// @codingStandardsIgnoreStart
if (class_exists('\Mageplaza\Osc\Model\CheckoutManagement')) {

    class CheckoutManagement extends \Mageplaza\Osc\Model\CheckoutManagement
    {
        /**
         * @var PaymentGroupTransaction
         */
        private $paymentGroupTransaction;

        /**
         * @param PaymentGroupTransaction $paymentGroupTransaction
         */
        public function __construct(PaymentGroupTransaction $paymentGroupTransaction)
        {
            $this->paymentGroupTransaction = $paymentGroupTransaction;
        }

        /**
         * Block updating the item qty when group transaction order already started
         *
         * @param int       $cartId
         * @param int       $itemId
         * @param int|float $itemQty
         *
         * @throws CouldNotSaveException
         *
         * @return mixed
         */
        public function updateItemQty(int $cartId, int $itemId, $itemQty)
        {
            /** @phpstan-ignore-next-line */
            if ($this->getAlreadyPaid($this->checkoutSession->getQuote()) > 0) {
                throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
            }

            /** @phpstan-ignore-next-line */
            return parent::updateItemQty($cartId, $itemId, $itemQty);
        }

        /**
         * Block remove the item qty when group transaction order already started
         *
         * @param int $cartId
         * @param int $itemId
         *
         * @throws CouldNotSaveException
         *
         * @return mixed
         */
        public function removeItemById(int $cartId, int $itemId)
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
         * @param Quote $quote
         *
         * @return float
         */
        private function getAlreadyPaid(Quote $quote): float
        {
            $groupTransaction = $this->paymentGroupTransaction;

            return $groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
        }
    }

} else {
    class CheckoutManagement
    {
    }
}

// @codingStandardsIgnoreEnd
