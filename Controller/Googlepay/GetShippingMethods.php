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
namespace Buckaroo\Magento2\Controller\Googlepay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\GooglepayFormatData;
use Buckaroo\Magento2\Model\Service\QuoteService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;

class GetShippingMethods extends AbstractGooglepay
{
    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var GooglepayFormatData
     */
    private $googlepayFormatData;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param RequestInterface        $request
     * @param BuckarooLoggerInterface $logger
     * @param QuoteService            $quoteService
     * @param GooglepayFormatData     $googlepayFormatData
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger,
        QuoteService $quoteService,
        GooglepayFormatData $googlepayFormatData
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->quoteService         = $quoteService;
        $this->googlepayFormatData  = $googlepayFormatData;
    }

    /**
     * Return Shipping Methods
     */
    public function execute()
    {
        $postValues = $this->getParams();
        $this->logger->addDebug(sprintf(
            '[GooglePay] | [Controller] | [%s:%s] - Get Shipping Methods | Request: %s',
            __METHOD__,
            __LINE__,
            var_export($postValues, true)
        ));

        $data = [];
        $errorMessage = false;

        // Support both 'wallet' (legacy) and 'address' (inline) formats
        $addressData = $postValues['wallet'] ?? $postValues['address'] ?? null;

        if (!empty($postValues) && $addressData) {
            try {
                // Get or create cart
                $cartHash = $postValues['id'] ?? null;

                // Decode address if it's a JSON string (do this FIRST)
                if (is_string($addressData)) {
                    $addressData = json_decode($addressData, true);
                }

                // If no cart exists yet (product page flow), create temporary quote with product
                if (!$cartHash && isset($postValues['product'])) {
                    $this->logger->addDebug('[GooglePay] No cart hash, creating temporary quote with product');
                    $productData = is_string($postValues['product'])
                        ? json_decode($postValues['product'], true)
                        : $postValues['product'];

                    // Create quote with product via Add service
                    $addPayload = [
                        'product' => $productData,
                        'wallet' => $addressData  // Already decoded as array
                    ];

                    // Use Add service to create quote
                    $addService = \Magento\Framework\App\ObjectManager::getInstance()
                        ->get(\Buckaroo\Magento2\Service\Googlepay\Add::class);
                    $result = $addService->process($addPayload);

                    if (isset($result['error']) && $result['error']) {
                        $errorMessage = $result['error'];
                        $this->logger->addDebug('[GooglePay] Error creating quote: ' . $errorMessage);
                        return $this->commonResponse([], $errorMessage);
                    }

                    // Quote is now in session, get it
                    $this->quoteService->getQuote();
                } else {
                    $this->quoteService->getQuote($cartHash);
                }

                // Process shipping address from Google Pay data
                $shippingAddressRequest = $this->googlepayFormatData->getShippingAddressObject($addressData);

                $this->quoteService->addAddressToQuote($shippingAddressRequest);

                //Set Payment Method
                $this->quoteService->setPaymentMethod(Googlepay::CODE);

                // Retrieve shipping methods.
                $shippingMethodsResult = [];
                if (!$this->quoteService->getQuote()->getIsVirtual()) {
                    $shippingMethods = $this->quoteService->getAvailableShippingMethods();
                    $this->logger->addDebug('[GooglePay] Available shipping methods count: ' . count($shippingMethods));

                    if (empty($shippingMethods)) {
                        $errorMessage = __(
                            'Google Pay payment failed, because no shipping methods were found for the selected address. ' .
                            'Please select a different shipping address within the pop-up or within your Google Pay Wallet.'
                        );
                    } else {
                        // Set default shipping method using the first method.
                        $firstMethod = reset($shippingMethods);
                        $this->quoteService->setShippingMethod($firstMethod['method_code']);
                        $shippingMethodsResult = $shippingMethods;
                    }
                }

                // Recalculate totals.
                $this->quoteService->calculateQuoteTotals();

                // Gather totals.
                $totals = $this->quoteService->gatherTotals();

                $data = [
                    'shipping_methods' => $shippingMethodsResult,
                    'totals'           => $totals
                ];
            } catch (NoSuchEntityException | LocalizedException $exception) {
                $this->logger->addDebug(sprintf(
                    '[GooglePay] | [Controller] | [%s:%s] - Get Shipping Methods | ERROR: %s',
                    __METHOD__,
                    __LINE__,
                    $exception->getMessage()
                ));
                $errorMessage = __('Get shipping methods failed');
            }
        } else {
            $errorMessage = __('Details from Wallet GooglePay are not received.');
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
