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

use Closure;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class CartManagement
{
    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        LockManagerWrapper $lockManager,
        LoggerInterface $logger
    ) {
        $this->lockManager = $lockManager;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * Places an order for a specified cart.
     *
     * @param CartManagementInterface $cardManagement
     * @param Closure                $proceed
     * @param int $cartId The cart ID.
     * @param PaymentInterface|null $paymentMethod
     * @throws CouldNotSaveException
     * @return int Order ID.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundPlaceOrder(
        CartManagementInterface $cardManagement,
        Closure $proceed,
        $cartId,
        ?PaymentInterface $paymentMethod = null
    ) {
        // Early check: if we can determine it's not a Buckaroo payment from the payment method parameter
        if ($paymentMethod && !$this->isBuckarooPaymentMethod($paymentMethod->getMethod())) {
            return $proceed($cartId, $paymentMethod);
        }

        try {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->getActive($cartId);
            $orderIncrementID = $quote->getReservedOrderId();

            if ($quote instanceof Quote &&
                $orderIncrementID !== null &&
                $this->isBuckarooPayment($quote)
            ) {
                try {
                    $orderIncrementID = $quote->getReservedOrderId();
                    $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

                    if (!$lockAcquired) {
                        throw new CouldNotSaveException(__("Cannot lock payment process"));
                    }
                    return $proceed($cartId, $paymentMethod);
                } finally {
                    $this->lockManager->unlockOrder($orderIncrementID);
                }
            }
        } catch (NoSuchEntityException $e) {
            // Quote is already inactive (e.g., for offline payment methods)
            // This is expected behavior for offline payments like "Check Money"
            $this->logger->debug(
                'Buckaroo CartManagement plugin: Quote already inactive for cartId',
                [
                    'cartId' => $cartId,
                    'paymentMethod' => $paymentMethod ? $paymentMethod->getMethod() : 'unknown',
                    'message' => $e->getMessage()
                ]
            );
            // Just proceed with the normal order placement flow
        }

        return $proceed($cartId, $paymentMethod);
    }


    private function isBuckarooPayment(Quote $quote)
    {
        return $this->isBuckarooPaymentMethod($quote->getPayment()->getMethod());
    }

    /**
     * Check if a payment method is a Buckaroo payment method
     *
     * @param string $paymentMethod
     * @return bool
     */
    private function isBuckarooPaymentMethod(string $paymentMethod): bool
    {
        return strpos($paymentMethod, "buckaroo_magento2_") !== false;
    }
}
