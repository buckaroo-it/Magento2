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
        private PaymentGroupTransaction $paymentGroupTransaction;

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
         * @param int $cartId
         * @param int $itemId
         * @param int|float $itemQty
         * @return mixed
         * @throws CouldNotSaveException
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
         * @return mixed
         * @throws CouldNotSaveException
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
         * @return float
         */
        private function getAlreadyPaid(Quote $quote): float
        {
            $groupTransaction = $this->paymentGroupTransaction;

            return $groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
        }
    }

    /**
     * Block updating the item qty when group transaction order already started
     *
     * @param \Mageplaza\Osc\Model\CheckoutManagement $subject
     * @param int $cartId
     * @param int $itemId
     * @param int|float $itemQty
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeUpdateItemQty(
        \Mageplaza\Osc\Model\CheckoutManagement $subject,
        int $cartId,
        int $itemId,
                                                $itemQty
    ) {
        if ($this->getAlreadyPaid($this->checkoutSession->getQuote()) > 0) {
            throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
        }

        return [$cartId, $itemId, $itemQty];
    }

    /**
     * Block remove the item qty when group transaction order already started
     *
     * @param \Mageplaza\Osc\Model\CheckoutManagement $subject
     * @param int $cartId
     * @param int $itemId
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeRemoveItemById(
        \Mageplaza\Osc\Model\CheckoutManagement $subject,
        int $cartId,
        int $itemId
    ) {
        if ($this->getAlreadyPaid($this->checkoutSession->getQuote()) > 0) {
            throw new CouldNotSaveException(__('Action is blocked, please finish current order'));
        }

        return [$cartId, $itemId];
    }

    /**
     * Get quote already paid amount
     *
     * @param Quote $quote
     * @return float
     */
    private function getAlreadyPaid(Quote $quote): float
    {
        return $this->paymentGroupTransaction->getAlreadyPaid($quote->getReservedOrderId());
    }
}
