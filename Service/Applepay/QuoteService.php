<?php

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class QuoteService
{
    /**
     * @var Log $logging
     */
    private $logging;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CheckoutSession $checkoutSession
     * @param Log $logging
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CheckoutSession $checkoutSession,
        Log $logging,
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->checkoutSession = $checkoutSession;
        $this->logging = $logging;
    }

    /**
     * Get checkout quote instance by cart Hash
     *
     * @param int|string|null $cartHash
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote($cartHash)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        if ($cartHash) {
            try {
                $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
                $cart = $this->cartRepository->get($cartId);
            } catch (NoSuchEntityException $exception) {
                throw new NoSuchEntityException(
                    __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => $cartHash])
                );
            }
        } else {
            try {
                $cart = $this->checkoutSession->getQuote();
            } catch (\Exception $exception) {
                throw new NoSuchEntityException(
                    __('Could not get checkout quote instance by current session')
                );
            }
        }

        return $cart;
    }
}
