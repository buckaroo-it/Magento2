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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Magento\Framework\Exception\CouldNotSaveException;

class CartManagement
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;


    /**
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;


    public function __construct(
        CartRepositoryInterface $quoteRepository,
        LockManagerWrapper $lockManager
    ) {
        $this->lockManager = $lockManager;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Places an order for a specified cart.
     *
     * @param  CartManagementInterface                            $cardManagement
     * @param  Closure                                           $proceed
     * @param  int                                                $cartId         The cart ID.
     * @param  PaymentInterface|null                              $paymentMethod
     * @return int                                                Order ID.
     * @throws CouldNotSaveException|NoSuchEntityException
     */
    public function aroundPlaceOrder(
        CartManagementInterface $cardManagement,
        Closure $proceed,
        $cartId,
        ?PaymentInterface $paymentMethod = null
    ) {
        /** @var Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        if ($quote instanceof Quote &&
            $quote->getReservedOrderId() !== null &&
            $this->isBuckarooPayment($quote)
        ) {
            $orderIncrementID = $quote->getReservedOrderId();
            $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

            if (!$lockAcquired) {
                throw new CouldNotSaveException(__("Cannot lock payment process"));

            }
            $response = $proceed($cartId, $paymentMethod);

            $this->lockManager->unlockOrder($orderIncrementID);
            return $response;
        }

        return $proceed($cartId, $paymentMethod);
    }


    private function isBuckarooPayment(Quote $quote)
    {
        return strpos($quote->getPayment()->getMethod(), "buckaroo_magento2_") !== false;
    }
}
