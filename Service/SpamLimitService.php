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
    private Session $checkoutSession;

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
     * @return void
     * @throws LimitReachException
     */
    public function updateRateLimiterCount(MethodInterface $paymentMethodInstance)
    {

        if (!$this->isSpamLimitActive($paymentMethodInstance)) {
            return;
        }

        $method = $paymentMethodInstance->getCode();
        try {
            $quoteId = $this->getQuote()->getId();
        } catch (NoSuchEntityException $e) {
        } catch (LocalizedException $e) {
        }
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
     * @return boolean
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
     * @param array $storage
     *
     * @return void
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
            throw new LimitReachException($limitReachMessage);
        }
    }

    /**
     * Check if the spam limit is reached
     *
     * @param array $storage
     * @param MethodInterface $paymentMethodInstance
     * @return boolean
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
        $limit = intval($limit);

        $method = $paymentMethodInstance->getCode();
        $quoteId = $this->getQuote()->getId();

        $attempts = 0;
        if (isset($storage[$quoteId][$method])) {
            $attempts = $storage[$quoteId][$method];
        }

        return $attempts >= $limit;
    }

    /**
     * Get Quote from Checkout Session
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Update payment with the user message, update session in order to restore the quote
     *
     * @param mixed $payment
     * @param string $message
     *
     * @return void
     */
    public function setMaxAttemptsFlags($payment, string $message)
    {
        $this->checkoutSession->setRestoreQuoteLastOrder($payment->getOrder()->getId());
        $this->checkoutSession->setBuckarooFailedMaxAttempts(true);
        $payment->setAdditionalInformation(BuckarooAdapter::PAYMENT_ATTEMPTS_REACHED_MESSAGE, $message);
    }
}