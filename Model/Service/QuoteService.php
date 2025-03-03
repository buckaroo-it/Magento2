<?php
declare(strict_types=1);

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License.
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
 * @copyright  Copyright (c) Buckaroo B.V.
 * @license    https://tldrlegal.com/license/mit-license
 */
namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Service\QuoteBuilderInterfaceFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;

class QuoteService
{
    private Log $logger;

    private QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory;

    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    private CartRepositoryInterface $cartRepository;

    private CheckoutSession $checkoutSession;

    private ShippingMethodsService $shippingMethodsService;

    private AddProductToCartService $addProductToCartService;

    private QuoteAddressService $quoteAddressService;

    private ?Quote $quote = null;

    /**
     * @param Log $logger
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AddProductToCartService $addProductToCartService
     * @param QuoteAddressService $quoteAddressService
     * @param ShippingMethodsService $shippingMethodsService
     * @param CheckoutSession $checkoutSession
     * @param QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory
     */
    public function __construct(
        Log $logger,
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        AddProductToCartService $addProductToCartService,
        QuoteAddressService $quoteAddressService,
        ShippingMethodsService $shippingMethodsService,
        CheckoutSession $checkoutSession,
        QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory
    ) {
        $this->logger                      = $logger;
        $this->cartRepository              = $cartRepository;
        $this->maskedQuoteIdToQuoteId      = $maskedQuoteIdToQuoteId;
        $this->checkoutSession             = $checkoutSession;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
        $this->shippingMethodsService      = $shippingMethodsService;
        $this->addProductToCartService     = $addProductToCartService;
        $this->quoteAddressService         = $quoteAddressService;
    }

    /**
     * Retrieve the checkout quote instance.
     *
     * @param int|string|null $cartHash
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote($cartHash = null): CartInterface
    {
        if ($this->quote instanceof Quote) {
            return $this->quote;
        }

        if ($cartHash) {
            try {
                $cartId = (int)$this->maskedQuoteIdToQuoteId->execute((string)$cartHash);
                $this->quote = $this->cartRepository->get($cartId);
            } catch (NoSuchEntityException $exception) {
                throw new NoSuchEntityException(
                    __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => $cartHash])
                );
            }
        } else {
            try {
                $this->quote = $this->checkoutSession->getQuote();
            } catch (\Exception $exception) {
                throw new NoSuchEntityException(__('Could not get checkout quote instance by current session'));
            }
        }

        return $this->quote;
    }

    /**
     * Retrieve an empty quote by removing all items.
     *
     * @param int|string|null $cartHash
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getEmptyQuote($cartHash = null): CartInterface
    {
        $this->quote = $this->getQuote($cartHash);
        $this->quote->removeAllItems();
        return $this->quote;
    }

    /**
     * Create a new quote using form data.
     *
     * @param string $formData
     * @return Quote
     * @throws Exception
     */
    public function createQuote(string $formData): Quote
    {
        try {
            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($formData);
            $this->quote = $quoteBuilder->build();
            return $this->quote;
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[CREATE_QUOTE] | [Service] | [%s:%s] - Create quote | ERROR: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
            throw new Exception(__('Failed to create quote'), 1, $th);
        }
    }

    /**
     * Set the payment method on the quote.
     *
     * @param string $paymentMethod
     * @return Quote
     */
    public function setPaymentMethod(string $paymentMethod): Quote
    {
        $payment = $this->quote->getPayment();
        $payment->setMethod($paymentMethod);
        $this->quote->setPayment($payment);
        return $this->quote;
    }

    /**
     * Calculate and save quote totals.
     *
     * @return Quote
     */
    public function calculateQuoteTotals(): Quote
    {
        $this->quote->setStoreId($this->quote->getStore()->getId());

        if ($this->quote->getCustomerEmail() === null) {
            $this->quote->setCustomerEmail('no-reply@example.com');
        }

        $this->quote->setTotalsCollectedFlag(false)
            ->collectTotals();

        $this->cartRepository->save($this->quote);

        return $this->quote;
    }

    /**
     * Gather and format totals from the quote.
     *
     * @return array
     */
    public function gatherTotals(): array
    {
        $quoteTotals = $this->quote->getTotals();
        $shippingAddress = $this->quote->getShippingAddress();
        $shippingTotalInclTax = $shippingAddress ? (float)$shippingAddress->getData('shipping_incl_tax') : 0.0;

        $totals = [
            'subtotal'    => (float)$quoteTotals['subtotal']->getValue(),
            'discount'    => isset($quoteTotals['discount']) ? (float)$quoteTotals['discount']->getValue() : 0.0,
            'shipping'    => $shippingTotalInclTax,
            'grand_total' => (float)$quoteTotals['grand_total']->getValue(),
        ];

        if ($this->quote->getSubtotal() != $this->quote->getSubtotalWithDiscount()) {
            $totals['discount'] = round(
                $this->quote->getSubtotalWithDiscount() - $this->quote->getSubtotal(),
                2
            );
        }

        return $totals;
    }

    /**
     * Set the shipping method on the quote's shipping address.
     *
     * @param string $methodCode
     * @return void
     * @throws NoSuchEntityException
     */
    public function setShippingMethod(string $methodCode): void
    {
        $this->getQuote()->getShippingAddress()->setShippingMethod($methodCode);
    }

    /**
     * Add a product to the cart.
     *
     * @param DataObject $product
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function addProductToCart(DataObject $product): void
    {
        $this->quote = $this->addProductToCartService->addProductToCart($product, $this->quote);
    }

    /**
     * Add a shipping address to the quote.
     *
     * @param mixed $shippingAddressRequest
     * @return void
     * @throws NoSuchEntityException|LocalizedException
     */
    public function addAddressToQuote($shippingAddressRequest): void
    {
        $this->quote = $this->quoteAddressService->addAddressToQuote($shippingAddressRequest, $this->quote);
    }

    /**
     * Retrieve available shipping methods for the quote.
     *
     * @return array
     */
    public function getAvailableShippingMethods(): array
    {
        return $this->shippingMethodsService->getAvailableShippingMethods(
            $this->quote,
            $this->quote->getShippingAddress()
        );
    }

    /**
     * Set the first available shipping method on the quote.
     *
     * @return Quote
     */
    public function addFirstShippingMethod(): Quote
    {
        return $this->shippingMethodsService->addFirstShippingMethod(
            $this->quote->getShippingAddress(),
            $this->quote
        );
    }

    /**
     * Return the current quote object.
     *
     * @return Quote
     */
    public function getQuoteObject(): Quote
    {
        return $this->quote;
    }
}
