<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\Service\AddProductToCartService;
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Buckaroo\Magento2\Model\Service\ShippingMethodsService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\Service\QuoteService;

class Add
{
    /**
     * @var Log
     */
    private $logger;

    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var AddProductToCartService
     */
    private $addProductToCartService;

    /**
     * @var QuoteAddressService
     */
    private $quoteAddressService;

    /**
     * @var ApplePayFormatData
     */
    private $applePayFormatData;

    /**
     * @var ShippingMethodsService
     */
    private $shippingMethodsService;


    public function __construct(
        Log                     $logger,
        QuoteService            $quoteService,
        AddProductToCartService $addProductToCartService,
        QuoteAddressService     $quoteAddressService,
        ApplePayFormatData      $applePayFormatData,
        ShippingMethodsService  $shippingMethodsService
    ) {
        $this->logger = $logger;
        $this->quoteService = $quoteService;
        $this->addProductToCartService = $addProductToCartService;
        $this->quoteAddressService = $quoteAddressService;
        $this->applePayFormatData = $applePayFormatData;
        $this->shippingMethodsService = $shippingMethodsService;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws ExpressMethodsException
     */
    public function process($request)
    {
        try {
            // Get Cart
            $cartHash = $request['id'] ?? null;
            $cart = $this->quoteService->getEmptyQuote($cartHash);

            // Add product to cart
            $product = $this->applePayFormatData->getProductObject($request['product']);
            $cart = $this->addProductToCartService->addProductToCart($product, $cart);

            // Get Shipping Address From Request
            $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($request['wallet']);

            // Add Shipping Address on Quote
            $cart = $this->quoteAddressService->addAddressToQuote($shippingAddressRequest, $cart);
            $cart = $this->quoteAddressService->assignAddressToQuote($cart->getShippingAddress(), $cart);

            // Get Shipping Methods
            $shippingMethods = $this->shippingMethodsService->getAvailableShippingMethods($cart, $cart->getShippingAddress());

            //Set Payment Method
            $cart = $this->quoteService->setPaymentMethod($cart, Applepay::CODE);

            // Calculate Quote Totals
            $cart = $this->quoteService->calculateQuoteTotals($cart);

            $totals = $this->quoteService->gatherTotals($cart->getShippingAddress(), $cart->getTotals());
            if ($cart->getSubtotal() != $cart->getSubtotalWithDiscount()) {
                $totals['discount'] = round($cart->getSubtotalWithDiscount() - $cart->getSubtotal(), 2);
            }

            return [
                'shipping_methods' => $shippingMethods,
                'totals' => $totals
            ];
        } catch (\Exception $exception) {
            $this->logger->addDebug(__METHOD__ . '|exception|' . $exception->getMessage());
            return false;
        }
    }
}
