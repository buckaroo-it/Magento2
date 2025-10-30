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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Method\Applepay;
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
     * @param Log                $logger
     * @param QuoteService       $quoteService
     * @param ApplePayFormatData $applePayFormatData
     */
    public function __construct(
        Log $logger,
        QuoteService $quoteService,
        ApplePayFormatData $applePayFormatData
    ) {
        $this->logger = $logger;
        $this->quoteService = $quoteService;
        $this->applePayFormatData = $applePayFormatData;
    }

    /**
     * Add Product to Cart on Apple Pay
     *
     * @param  array       $request
     * @return array|false
     */
    public function process(array $request)
    {
        try {
            // Get Cart (empty it first)
            $cartHash = $request['id'] ?? null;
            $this->quoteService->getEmptyQuote($cartHash);

            // Add product to cart
            $product = $this->applePayFormatData->getProductObject($request['product']);
            $this->quoteService->addProductToCart($product);

            $shippingMethodsResult = [];
            $this->logger->addDebug('Add::process - before shipping address processing');

            // If the quote is not virtual, then process shipping address and methods
            if (!$this->quoteService->getQuote()->getIsVirtual()) {
                // Validate wallet data exists
                if (empty($request['wallet'])) {
                    throw new \Exception('Wallet data is missing in the request.');
                }

                // Get Shipping Address From Request using wallet data
                $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($request['wallet']);

                // Add Shipping Address on Quote (only once)
                $this->quoteService->addAddressToQuote($shippingAddressRequest);

                // Get Shipping Methods from the updated quote
                $shippingMethods = $this->quoteService->getAvailableShippingMethods();
                $this->logger->addDebug('Add::process - shipping methods retrieved', json_encode($shippingMethods));

                if (!empty($shippingMethods)) {
                    foreach ($shippingMethods as $shippingMethod) {
                        $shippingMethodsResult[] = [
                            'carrier_title' => $shippingMethod['carrier_title'],
                            'price_incl_tax' => round($shippingMethod['price_incl_tax'], 2),
                            'method_code' => $shippingMethod['method_code'],
                            'method_title' => $shippingMethod['method_title'],
                        ];
                    }
                    // Set the first available shipping method if available
                    if (!empty($shippingMethodsResult)) {
                        $this->quoteService->setShippingMethod($shippingMethodsResult[0]['method_code']);
                    }
                }
            }

            $this->logger->addDebug('Add::process - shipping methods result', json_encode($shippingMethodsResult));

            // Set Payment Method to Applepay
            $this->quoteService->setPaymentMethod(Applepay::PAYMENT_METHOD_CODE);

            // Calculate Quote Totals and save quote
            $this->quoteService->calculateQuoteTotals();

            // Gather Totals from the quote
            $totals = $this->quoteService->gatherTotals();

            return [
                'shipping_methods' => $shippingMethodsResult,
                'totals'           => $totals,
            ];
        } catch (\Exception $exception) {
            $this->logger->addError(sprintf(
                '[ApplePay] | [Service\Applepay\Add::process] - [ERROR]: %s',
                $exception->getMessage()
            ));
            return false;
        }
    }
}
