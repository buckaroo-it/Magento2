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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Method\LimitReachException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class SpamLimitService
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param Session $checkoutSession
     */
    public function __construct(
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Update session when a failed attempt is made for the quote & method
     *
     * @param MethodInterface $paymentMethodInstance
     *
     * @throws LimitReachException
     */
    public function updateRateLimiterCount(MethodInterface $paymentMethodInstance)
    {

        if (!$this->isSpamLimitActive($paymentMethodInstance)) {
            return;
        }

        $method = $paymentMethodInstance->getCode();
        $quoteId = $this->getQuote()->getId();

        $storage = $this->getPaymentAttemptsStorage();
        if (!isset($storage[$quoteId])) {
            $storage[$quoteId] = [$method => 0];
        }

        if (!isset($storage[$quoteId][$method])) {
            $storage[$quoteId][$method] = 0;
        }

        $storage[$quoteId][$method]++;

        $this->checkoutSession->setBuckarooRateLimiterStorage(json_encode($storage));

        $this->checkForSpamLimitReach($paymentMethodInstance, $storage);
    }

    /**
     * Check if config spam limit is active
     *
     * @param MethodInterface $paymentMethodInstance
     *
     * @return bool
     */
    public function isSpamLimitActive(MethodInterface $paymentMethodInstance): bool
    {
        return $paymentMethodInstance->getConfigData('spam_prevention') == 1;
    }

    /**
     * Retrieve and format number of payment attempts
     *
     * @return array
     */
    public function getPaymentAttemptsStorage(): array
    {
        $storage = $this->checkoutSession->getBuckarooRateLimiterStorage();
        if ($storage === null) {
            return [];
        }

        $storage = json_decode($storage, true);
        if (!is_array($storage)) {
            $storage = [];
        }

        return $storage;
    }

    /**
     * Check if the spamming limit is reached
     *
     * @param MethodInterface $paymentMethodInstance
     * @param array           $storage
     *
     * @throws LimitReachException
     */
    private function checkForSpamLimitReach(MethodInterface $paymentMethodInstance, $storage): void
    {
        $limitReachMessage = __('Cannot create order, maximum payment attempts reached');

        $storedReachMessage = $paymentMethodInstance->getConfigData('spam_message');

        if (is_string($storedReachMessage) && trim($storedReachMessage) > 0) {
            $limitReachMessage = $storedReachMessage;
        }

        if ($this->isSpamLimitReached($paymentMethodInstance, $storage)) {
            throw new LimitReachException((string)$limitReachMessage);
        }
    }

    /**
     * Check if the spam limit is reached
     *
     * @param MethodInterface $paymentMethodInstance
     * @param array $storage
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isSpamLimitReached(MethodInterface $paymentMethodInstance, array $storage): bool
    {
        if ($paymentMethodInstance->getConfigData('spam_prevention') != 1) {
            return false;
        }

        $limit = $paymentMethodInstance->getConfigData('spam_attempts');

        if (!is_scalar($limit)) {
            $limit = 10;
        }
        $limit = (int)$limit;

        $method = $paymentMethodInstance->getCode();

        try {
            $quote = $this->getQuote();

            // Check if spam limit was reached and stored on quote payment (persists across quote restoration)
            if ($quote && $quote->getPayment()) {
                $spamLimitReached = $quote->getPayment()
                    ->getAdditionalInformation('buckaroo_spam_limit_reached_' . $method);
                if ($spamLimitReached === true) {
                    return true;
                }
            }

            // Check session storage (for current quote)
            $quoteId = $quote ? $quote->getId() : null;
            if ($quoteId && isset($storage[$quoteId][$method])) {
                $attempts = $storage[$quoteId][$method];
                return $attempts >= $limit;
            }

            // Check if there's a cancelled order ID (quote was restored after spam limit)
            if ($quote && $quote->getPayment()) {
                $cancelledOrderId = $quote->getPayment()
                    ->getAdditionalInformation('buckaroo_cancel_order_id');
                if ($cancelledOrderId) {
                    // Look up attempts from the original quote ID that created this order
                    foreach ($storage as $methods) {
                        if (isset($methods[$method]) && $methods[$method] >= $limit) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // If we can't get quote, default to session storage check
        }

        return false;
    }

    /**
     * Get Quote from Checkout Session
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @return CartInterface|Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Update payment with the user message, update session in order to restore the quote
     *
     * @param mixed  $payment
     * @param string $message
     */
    public function setMaxAttemptsFlags($payment, string $message)
    {
        $this->checkoutSession->setRestoreQuoteLastOrder($payment->getOrder()->getId());
        $this->checkoutSession->setBuckarooFailedMaxAttempts(true);
        $payment->setAdditionalInformation(BuckarooAdapter::PAYMENT_ATTEMPTS_REACHED_MESSAGE, $message);
    }
}
