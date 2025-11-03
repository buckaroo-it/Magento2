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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
use Buckaroo\Magento2\Model\Service\QuoteService;
use Buckaroo\Magento2\Service\ExpressPayment\ProductValidationService;

class Add
{
    /**
     * @var BuckarooLoggerInterface
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
     * @var ProductValidationService
     */
    private $productValidationService;

    /**
     * @param BuckarooLoggerInterface  $logger
     * @param QuoteService             $quoteService
     * @param ApplePayFormatData       $applePayFormatData
     * @param ProductValidationService $productValidationService
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        QuoteService $quoteService,
        ApplePayFormatData $applePayFormatData,
        ProductValidationService $productValidationService
    ) {
        $this->logger = $logger;
        $this->quoteService = $quoteService;
        $this->applePayFormatData = $applePayFormatData;
        $this->productValidationService = $productValidationService;
    }

    /**
     * Add Product to Cart on Apple Pay
     *
     * @param array $request
     *
     * @return array|false
     */
    public function process(array $request)
    {
        try {
            // Validate product before proceeding
            $productData = $request['product'] ?? [];
            $productId = $productData['id'] ?? null;
            $qty = $productData['qty'] ?? 1;
            $selectedOptions = $productData['selected_options'] ?? [];

            if (!$productId) {
                throw new \Exception('Product ID is required.');
            }

            $validation = $this->productValidationService->validateProduct(
                (int)$productId,
                $selectedOptions,
                (float)$qty
            );
            if (!$validation['is_valid']) {
                $errorMessage = 'Product validation failed: ' . implode(', ', $validation['errors']);
                throw new \Exception($errorMessage);
            }

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
                    $this->quoteService->setShippingMethod($shippingMethodsResult[0]['method_code']);
                }
            }

            $this->logger->addDebug('Add::process - shipping methods result', json_encode($shippingMethodsResult));

            // Set Payment Method to Applepay
            $this->quoteService->setPaymentMethod(Applepay::CODE);

            // Calculate Quote Totals and save quote
            $this->quoteService->calculateQuoteTotals();

            // Gather Totals from the quote
            $totals = $this->quoteService->gatherTotals();

            return [
                'shipping_methods' => $shippingMethodsResult,
                'totals'           => $totals
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
