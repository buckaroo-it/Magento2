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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\QuoteBuilderInterfaceFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuoteService
{
    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;
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
     * @var ShippingMethodsService
     */
    private ShippingMethodsService $shippingMethodsService;

    /**
     * @var AddProductToCartService
     */
    private AddProductToCartService $addProductToCartService;

    /**
     * @var QuoteAddressService
     */
    private QuoteAddressService $quoteAddressService;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AddProductToCartService $addProductToCartService
     * @param QuoteAddressService $quoteAddressService
     * @param ShippingMethodsService $shippingMethodsService
     * @param CheckoutSession $checkoutSession
     * @param QuoteBuilderInterfaceFactory $quoteBuilderInterfaceFactory
     */
    public function __construct(
        BuckarooLoggerInterface         $logger,
        CartRepositoryInterface         $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        AddProductToCartService         $addProductToCartService,
        QuoteAddressService             $quoteAddressService,
        ShippingMethodsService          $shippingMethodsService,
        CheckoutSession                 $checkoutSession,
        QuoteBuilderInterfaceFactory    $quoteBuilderInterfaceFactory
    ) {
        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->checkoutSession = $checkoutSession;
        $this->quoteBuilderInterfaceFactory = $quoteBuilderInterfaceFactory;
        $this->shippingMethodsService = $shippingMethodsService;
        $this->addProductToCartService = $addProductToCartService;
        $this->quoteAddressService = $quoteAddressService;
    }

    /**
     * Get checkout quote instance by cart Hash
     *
     * @param int|string|null $cartHash
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote($cartHash = null)
    {
        if ($cartHash) {
            try {
                $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
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
                throw new NoSuchEntityException(
                    __('Could not get checkout quote instance by current session')
                );
            }
        }

        return $this->quote;
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
        $this->quote = $this->getQuote($cartHash);
        $this->quote->removeAllItems();
        return $this->quote;
    }

    /**
     * Create quote if in product page
     *
     * @param string $formData
     *
     * @return Quote
     * @throws QuoteException
     */
    public function createQuote(string $formData): Quote
    {
        try {
            /** @var QuoteBuilderInterface $quoteBuilder */
            $quoteBuilder = $this->quoteBuilderInterfaceFactory->create();
            $quoteBuilder->setFormData($formData);
            $this->quote = $quoteBuilder->build();
            return $this->quote;
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[CREATE_QUOTE] | [Service] | [%s:%s] - Create quote if in product page | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
            throw new QuoteException(__("Failed to create quote"), 1, $th);
        }
    }

    /**
     * Set paypal payment method on quote
     *
     * @param string $paymentMethod
     *
     * @return Quote
     */
    public function setPaymentMethod($paymentMethod)
    {
        $payment = $this->quote->getPayment();
        $payment->setMethod($paymentMethod);
        $this->quote->setPayment($payment);

        return $this->quote;
    }

    /**
     * Calculate quote totals, set store id required for quote masking,
     * set customer email required for order validation
     *
     * @return Quote
     */
    public function calculateQuoteTotals()
    {
        $this->quote->setStoreId($this->quote->getStore()->getId());

        if ($this->quote->getCustomerEmail() === null) {
            $this->quote->setCustomerEmail('no-reply@example.com');
        }
        $this->quote
            ->setTotalsCollectedFlag(false)
            ->collectTotals();

        $this->cartRepository->save($this->quote);

        return $this->quote;
    }

    /**
     * Format Totals
     *
     * @return array
     */
    public function gatherTotals()
    {
        $quoteTotals = $this->quote->getTotals();

        $totals = [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $this->quote->getShippingAddress()->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
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
     * Add Product To Cart
     *
     * @param DataObject $product
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function addProductToCart($product)
    {
        $this->quote = $this->addProductToCartService->addProductToCart($product, $this->quote);
    }

    /**
     * Add Address To Cart
     *
     * @param $shippingAddressRequest
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function addAddressToQuote($shippingAddressRequest)
    {
        $this->quote = $this->quoteAddressService->addAddressToQuote($shippingAddressRequest, $this->quote);
    }

    /**
     * Get Available Shipping Methods for specific Address
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
     * Set First Shipping Method
     *
     * @return Quote
     */
    public function addFirstShippingMethod()
    {
        return $this->shippingMethodsService->addFirstShippingMethod(
            $this->quote->getShippingAddress(),
            $this->quote
        );
    }

    /**
     * Return modified quote
     *
     * @return Quote
     */
    public function getQuoteObject()
    {
        return $this->quote;
    }
}
