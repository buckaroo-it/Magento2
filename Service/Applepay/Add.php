<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
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
     * @var ApplePayFormatData
     */
    private $applePayFormatData;

    /**
     * @param Log $logger
     * @param QuoteService $quoteService
     * @param ApplePayFormatData $applePayFormatData
     */
    public function __construct(
        Log                     $logger,
        QuoteService            $quoteService,
        ApplePayFormatData      $applePayFormatData,
    ) {
        $this->logger = $logger;
        $this->quoteService = $quoteService;
        $this->applePayFormatData = $applePayFormatData;
    }

    /**
     * Add Product to Cart on Applepay
     *
     * @param $request
     * @return array|false
     */
    public function process($request)
    {
        try {
            // Get Cart
            $cartHash = $request['id'] ?? null;
            $this->quoteService->getEmptyQuote($cartHash);

            // Add product to cart
            $product = $this->applePayFormatData->getProductObject($request['product']);
            $this->quoteService->addProductToCart($product);

            // Get Shipping Address From Request
            $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($request['wallet']);

            // Add Shipping Address on Quote
            $this->quoteService->addAddressToQuote($shippingAddressRequest);

            // Get Shipping Methods
            $shippingMethods = $this->quoteService->getAvailableShippingMethods();

            //Set Payment Method
            $this->quoteService->setPaymentMethod(Applepay::CODE);

            // Calculate Quote Totals
            $this->quoteService->calculateQuoteTotals();

            // Get Totals
            $totals = $this->quoteService->gatherTotals();

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
