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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\Service\QuoteService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GetShippingMethods extends AbstractApplepay
{
    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var ApplePayFormatData
     */
    private $applePayFormatData;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param Log $logging
     * @param QuoteService $quoteService
     * @param ApplePayFormatData $applePayFormatData
     */
    public function __construct(
        JsonFactory        $resultJsonFactory,
        RequestInterface   $request,
        Log                $logging,
        QuoteService       $quoteService,
        ApplePayFormatData $applePayFormatData,
    ) {
        parent::__construct(
            $resultJsonFactory,
            $request,
            $logging
        );
        $this->quoteService = $quoteService;
        $this->applePayFormatData = $applePayFormatData;
    }

    /**
     * Return Shipping Methods
     *
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $postValues = $this->getParams();

        $data = [];
        $errorMessage = false;
        if (!empty($postValues) && isset($postValues['wallet'])) {
            try {
                // Get Cart
                $cartHash = $postValues['id'] ?? null;
                $this->quoteService->getEmptyQuote($cartHash);

                // Add product to cart
                $product = $this->applePayFormatData->getProductObject($postValues['product']);
                $this->quoteService->addProductToCart($product);

                // Get Shipping Address From Request
                $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($postValues['wallet']);

                // Add Shipping Address on Quote
                $this->quoteService->addAddressToQuote($shippingAddressRequest);

                // Get Shipping Methods
                $shippingMethods = $this->quoteService->getAvailableShippingMethods();
                if (count($shippingMethods) <= 0) {
                    $errorMessage = __(
                        'Apple Pay payment failed, because no shipping methods were found for the selected address. ' .
                        'Please select a different shipping address within the pop-up or within your Apple Pay Wallet.'
                    );
                }

                //Set Payment Method
                $this->quoteService->setPaymentMethod(Applepay::CODE);

                // Calculate Quote Totals
                $this->quoteService->calculateQuoteTotals();

                // Get Totals
                $totals = $this->quoteService->gatherTotals();

                $data = [
                    'shipping_methods' => $shippingMethods,
                    'totals' => $totals
                ];
            } catch (\Exception $exception) {
                $this->logging->addDebug(__METHOD__ . '|exception|' . $exception->getMessage());
                $errorMessage = __(
                    'Get shipping methods failed'
                );
            }
        } else {
            $errorMessage = __(
                'Details from Wallet ApplePay are not received.'
            );
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
