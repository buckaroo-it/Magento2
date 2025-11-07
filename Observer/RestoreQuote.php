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

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Checkout\Model\Session;
use Buckaroo\Magento2\Model\Service\Order;
use Buckaroo\Magento2\Model\Method\Payconiq;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemove;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

class RestoreQuote implements ObserverInterface
{
    private $checkoutSession;
    protected $accountConfig;
    private $helper;
    protected $quoteRepository;
    protected $orderService;
    protected $giftcardRemoveService;
    protected $groupTransaction;

    public function __construct(
        Session $checkoutSession,
        Account $accountConfig,
        Data $helper,
        CartRepositoryInterface $quoteRepository,
        Order $orderService,
        GiftcardRemove $giftcardRemoveService,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->accountConfig = $accountConfig;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->orderService = $orderService;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Restore Quote and Cancel LastRealOrder
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->helper->addDebug(__METHOD__ . '|1|' . var_export($this->checkoutSession->getData(), true));

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        $previousOrderId = $lastRealOrder->getId();

        if ($payment = $lastRealOrder->getPayment()) {
            if ($this->shouldSkipFurtherEventHandling() || $this->isPayconiqPaymentMethod($payment)) {
                $this->helper->addDebug(__METHOD__ . '|10|');
                return;
            }

            if ($this->accountConfig->getCartKeepAlive($lastRealOrder->getStore())) {
                $this->helper->addDebug(__METHOD__ . '|20|');

                $quote = $this->getActiveQuote();
                if ($quote) {
                    $this->processShippingAddress($quote);
                }

                if ($this->shouldRestoreQuote($lastRealOrder, $payment)) {
                    $this->helper->addDebug(__METHOD__ . '|40|');
                    $this->checkoutSession->restoreQuote();

                    if ($this->isFastCheckout($payment)) {
                        $this->clearRestoredQuoteAddresses($this->checkoutSession->getQuote());
                    }

                    $this->rollbackPartialPayment($lastRealOrder->getIncrementId());
                    $this->setOrderToCancel($previousOrderId);
                }
            }

            $this->helper->addDebug(__METHOD__ . '|50|');
            $this->helper->setRestoreQuoteLastOrder(false);
            $this->checkoutSession->unsBuckarooFailedMaxAttempts();
        }

        $this->helper->addDebug(__METHOD__ . '|55|');
    }

    /**
     * Get the active quote from the checkout session.
     *
     * @return Quote|null
     */
    private function getActiveQuote()
    {
        $quote = null;
        if ($this->checkoutSession->getQuote() && $this->checkoutSession->getQuote()->getId()) {
            try {
                $quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId());
            } catch (\Exception $e) {
                $this->helper->addError(__METHOD__ . '|Error fetching active quote: ' . $e->getMessage());
            }
        }
        return $quote;
    }

    /**
     * Process the shipping address of the quote.
     *
     * @param Quote $quote
     */
    private function processShippingAddress($quote)
    {
        $this->helper->addDebug(__METHOD__ . '|25|');
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && !$shippingAddress->getShippingMethod()) {
            $this->helper->addDebug(__METHOD__ . '|35|');
            try {
                $shippingAddress->load($shippingAddress->getAddressId());
            } catch (\Exception $e) {
                $this->helper->addError(__METHOD__ . '|Error loading shipping address: ' . $e->getMessage());
            }
        }
    }

    /**
     * Check if the quote should be restored.
     *
     * @param       $lastRealOrder
     * @param       $payment
     * @return bool
     */
    private function shouldRestoreQuote($lastRealOrder, $payment)
    {
        if ($payment->getAdditionalInformation(AbstractMethod::BUCKAROO_PAYMENT_IN_TRANSIT) === true) {
            $this->helper->addDebug(__METHOD__ . '|Payment in transit, not restoring quote to prevent duplicate orders');
            return false;
        }

        return (
            ($this->helper->getRestoreQuoteLastOrder() &&
                ($lastRealOrder->getData('state') === 'new') &&
                ($lastRealOrder->getData('status') === 'pending') &&
                $payment->getMethodInstance()->usesRedirect) || $this->canRestoreFailedFromSpam()
        );
    }

    /**
     * Clear addresses after quote restoration to ensure they are not unintentionally restored.
     *
     * @param Quote $quote
     */
    private function clearRestoredQuoteAddresses($quote)
    {
        if ($quote && $quote->getId()) {
            $quote->setCustomerEmail(null);

            // Remove existing addresses if they exist
            $this->clearAddress($quote, $quote->getBillingAddress());
            $this->clearAddress($quote, $quote->getShippingAddress());

            // Save the modified quote to ensure addresses are cleared
            try {
                $this->quoteRepository->save($quote);
                $this->helper->addDebug(__METHOD__ . '|Addresses cleared after restoreQuote()');
            } catch (\Exception $e) {
                $this->helper->addDebug(__METHOD__ . '|Error clearing addresses: ' . $e->getMessage());
            }
        }
    }


    /**
     * Clear address data and remove the address object from the quote.
     *
     * @param Quote $quote
     * @param       $address
     */
    private function clearAddress($quote, $address)
    {
        if ($address) {
            // Remove the address from the quote
            $quote->removeAddress($address->getId());

            // Optionally clear address data if needed to reset but keep structure intact
            $address->addData([]);
        }
    }

    /**
     * Check if the payment method is fastcheckout.
     *
     * @param       $payment
     * @return bool
     */
    private function isFastCheckout($payment)
    {
        return $payment->getMethod() === 'buckaroo_magento2_ideal' &&
            isset($payment->getAdditionalInformation()['issuer']) &&
            $payment->getAdditionalInformation()['issuer'] === 'fastcheckout';
    }

    /**
     * Check if the payment method should be skipped.
     *
     * @param       $payment
     * @return bool
     */
    private function isPayconiqPaymentMethod($payment)
    {
        return strpos($payment->getMethod(), 'buckaroo_magento2') === false ||
            in_array($payment->getMethod(), [Payconiq::PAYMENT_METHOD_CODE]);
    }

    public function canRestoreFailedFromSpam()
    {
        return $this->helper->getRestoreQuoteLastOrder() &&
            $this->checkoutSession->getBuckarooFailedMaxAttempts() === true;
    }

    public function shouldSkipFurtherEventHandling()
    {
        return false;
    }

    private function setOrderToCancel($previousOrderId)
    {
        $this->checkoutSession->getQuote()
            ->getPayment()
            ->setAdditionalInformation('buckaroo_cancel_order_id', $previousOrderId);
        $this->quoteRepository->save($this->checkoutSession->getQuote());
    }

    public function rollbackPartialPayment($incrementId)
    {
        try {
            $transactions = $this->groupTransaction->getGroupTransactionItems($incrementId);
            foreach ($transactions as $transaction) {
                $this->giftcardRemoveService->remove($transaction->getTransactionId(), $incrementId);
            }
        } catch (\Throwable $th) {
            $this->helper->addDebug(__METHOD__ . (string)$th);
        }
    }
}
