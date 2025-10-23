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

class CheckoutManagement
{
    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $paymentGroupTransaction;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @param PaymentGroupTransaction         $paymentGroupTransaction
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        PaymentGroupTransaction $paymentGroupTransaction,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->paymentGroupTransaction = $paymentGroupTransaction;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Block updating the item qty when group transaction order already started
     *
     * @param  \Mageplaza\Osc\Model\CheckoutManagement $subject
     * @param  int                                     $cartId
     * @param  int                                     $itemId
     * @param  int|float                               $itemQty
     * @throws CouldNotSaveException
     * @return array
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
     * @param  \Mageplaza\Osc\Model\CheckoutManagement $subject
     * @param  int                                     $cartId
     * @param  int                                     $itemId
     * @throws CouldNotSaveException
     * @return array
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
     * @param  Quote $quote
     * @return float
     */
    private function getAlreadyPaid(Quote $quote): float
    {
        return $this->paymentGroupTransaction->getAlreadyPaid($quote->getReservedOrderId());
    }
}
