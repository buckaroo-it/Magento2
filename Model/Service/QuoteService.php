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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Model\PaypalExpress\QuoteBuilderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use tests\unit\Magento\FunctionalTestFramework\Composer\ComposerInstallTest;

class QuoteService
{
    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;
    /**
     * @var \Buckaroo\Magento2\Model\Service\QuoteBuilderInterfaceFactory
     */
    protected $quoteBuilderInterfaceFactory;
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CheckoutSession $checkoutSession
     * @param QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory
     */
    public function __construct(
        CartRepositoryInterface         $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CheckoutSession                 $checkoutSession,
        QuoteBuilderInterfaceFactory    $quoteBuilderInterfaceFactory,
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->checkoutSession = $checkoutSession;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
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

    /**
     * Get empty checkout quote instance by cart Hash
     *
     * @param int|string|null $cartHash
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getEmptyQuote($cartHash)
    {
        $cart = $this->getQuote($cartHash);
        $cart->removeAllItems();
        return $cart;
    }

    /**
     * Create quote if in product page
     *
     * @param array $formData
     *
     * @return Quote
     * @throws QuoteException
     */
    public function createQuote(array $formData): Quote
    {
        try {
            /** @var QuoteBuilderInterface $quoteBuilder */
            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($formData);
            return $quoteBuilder->build();
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . $th->getMessage());
            throw new QuoteException(__("Failed to create quote"), 1, $th);
        }
    }

    /**
     * Set paypal payment method on quote
     *
     * @param Quote $quote
     * @param string $paymentMethod
     *
     * @return Quote
     */
    protected function setPaymentMethod($quote, $paymentMethod)
    {
        $payment = $quote->getPayment();
        $payment->setMethod($paymentMethod);
        $quote->setPayment($payment);

        return $quote;
    }
}
